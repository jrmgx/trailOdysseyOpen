<?php

namespace App\Service;

use App\Entity\User;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Lrf141\OAuth2\Client\Provider\Mastodon;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vazaha\Mastodon\ApiClient;
use Vazaha\Mastodon\Factories\ApiClientFactory;
use Vazaha\Mastodon\Helpers\UploadFile;
use Vazaha\Mastodon\Models\MediaAttachmentModel;
use Vazaha\Mastodon\Models\StatusModel;

class MastodonService
{
    /**
     * @var array<string, array{instanceUrl: string, clientKey: string, clientSecret: string}>
     */
    private readonly array $info;

    /**
     * @param array<int, string> $instances
     * @param array<int, string> $clientKeys
     * @param array<int, string> $clientSecrets
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(csv:MASTODON_INSTANCES)%')]
        array $instances,
        #[Autowire('%env(csv:MASTODON_CLIENT_KEYS)%')]
        array $clientKeys,
        #[Autowire('%env(csv:MASTODON_CLIENT_SECRETS)%')]
        array $clientSecrets,
        #[Autowire('%env(PROJECT_BASE_URL)%')]
        private readonly string $redirectUri,
    ) {
        $info = [];
        for ($i = 0; $i < \count($instances); ++$i) {
            $instance = self::normalizeInstanceUrl($instances[$i]);
            $info[$instance] = [
                'instanceUrl' => $instance,
                'clientKey' => $clientKeys[$i],
                'clientSecret' => $clientSecrets[$i],
            ];
        }
        $this->info = $info;
    }

    public function oAuthInit(Request $request, string $instanceUrl): string
    {
        $session = $request->getSession();
        $instanceUrl = self::normalizeInstanceUrl($instanceUrl);

        $provider = $this->getProvider($instanceUrl);
        $url = $provider->getAuthorizationUrl();
        $session->set('oauthState', $provider->getState());
        $session->set('instanceUrl', $instanceUrl);

        return $url;
    }

    public function oAuthHandle(Request $request): AccessToken|AccessTokenInterface
    {
        $session = $request->getSession();
        $state = $request->query->get('state');
        if (empty($state) || $state !== $session->get('oauthState')) {
            throw new \Exception('Invalid state.');
        }

        $instanceUrl = $session->get('instanceUrl');
        $provider = $this->getProvider($instanceUrl);

        return $provider->getAccessToken('authorization_code', [
            'code' => $request->query->get('code'),
        ]);
    }

    public function uploadMedia(User $user, string $filePath, string $description = ''): MediaAttachmentModel
    {
        // https://docs.joinmastodon.org/methods/media/#v2
        $client = $this->getClient($user);

        return $client->methods()->media()->v2(
            file: new UploadFile($filePath),
            description: $description,
        );
    }

    /**
     * @param array<int, MediaAttachmentModel> $mediaIds
     */
    public function postStatus(User $user, string $status = '', array $mediaIds = []): StatusModel
    {
        // https://docs.joinmastodon.org/methods/statuses/#create
        $client = $this->getClient($user);

        return $client->methods()->statuses()->create(
            status: $status,
            media_ids: array_map(fn (MediaAttachmentModel $m) => $m->id, $mediaIds),
            visibility: 'public',
            language: 'en', // TODO
        );
    }

    /**
     * @param array<int, MediaAttachmentModel> $mediaIds
     */
    public function editStatus(User $user, string $uri, string $status = '', array $mediaIds = []): StatusModel
    {
        $uriParts = explode('/', $uri);
        $id = end($uriParts);

        $client = $this->getClient($user);

        return $client->methods()->statuses()->edit(
            $id,
            status: $status,
            media_ids: array_map(fn (MediaAttachmentModel $m) => $m->id, $mediaIds),
        );
    }

    private function getProvider(string $instanceUrl): Mastodon
    {
        $redirectUri = $this->redirectUri . $this->urlGenerator->generate('user_connect_mastodon_code');
        $info = $this->info[$instanceUrl]
            ?? throw new \Exception('This instance does not exist.');

        return new Mastodon([
            'clientId' => $info['clientKey'],
            'clientSecret' => $info['clientSecret'],
            'redirectUri' => $redirectUri,
            'instance' => $instanceUrl,
            'scope' => 'profile write',
        ]);
    }

    private function getClient(User $user): ApiClient
    {
        $instanceUrl = $user->getMastodonInfo()['instanceUrl']
            ?? throw new \Exception('User does not seem to have configured Mastodon Connect.');
        $token = $user->getMastodonInfo()['accessToken'];

        $factory = new ApiClientFactory();
        $client = $factory->build();
        $client->setBaseUri($instanceUrl);
        $client->setAccessToken($token);

        return $client;
    }

    public static function normalizeInstanceUrl(string $instanceUrl): string
    {
        return 'https://' . preg_replace('`^https?://`i', '', $instanceUrl);
    }
}
