<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

class SmokeTest extends WebTestCase
{
    /**
     * This method will test each GET route from the project excepted those filtered by @see routeFilter().
     * The test will load the route and check if the http code is either 200 or 302.
     */
    public function testShow(): void
    {
        $client = self::createClientLoggedAs('jerome@gangneux.net');

        $urls = [
            '',
            '/login',
            '/register',
            '/trip',
            '/trip/new',
            '/trip/2908/edit',
            '/trip/2908/stages',
            '/trip/2908/segments',
            '/trip/2908/stages/4060/edit',
            '/trip/2908/routing/5079/edit',
            '/trip/2908/stages/new/48.888197770982316/-1.247978210449219/entry',
            '/trip/2908/geo/search/stage',
            '/trip/2908/interest/72/edit',
            '/trip/2908/interest/71/edit',
            '/trip/2908/interest/new/48.87758662245016/-1.2290954589843752/entry',
            '/trip/2908/geo/search/interest',
            '/trip/2908/interest/new/48.8534951/2.3483915/entry?name=paris',
            '/trip/2908/photo/48.80098420100443/-1.1784553527832033/new',
            '/trip/2908/segments/102/edit',
            '/trip/2908/segments/new/itinerary',
            '/trip/2908/gpx/new?onBoarding=0',
        ];

        foreach ($urls as $url) {
            $client->request('GET', $url);
            $response = $client->getResponse();
            $code = $response->getStatusCode();

            $this->assertTrue(200 === $code || 302 === $code, 'Url: ' . $url . ' failed with code: ' . $code);
        }

        $container = static::getContainer();
        /** @var RouterInterface $router */
        $router = $container->get(RouterInterface::class);
        $basicRoutes = array_filter($router->getRouteCollection()->all(), $this->routeFilter(...));
        foreach ($basicRoutes as $route) {
            $client->request('GET', $route->getPath());
            $response = $client->getResponse();
            $code = $response->getStatusCode();

            $this->assertTrue(200 === $code || 302 === $code, 'Url: ' . $route->getPath() . ' failed with code: ' . $code);
        }
    }

    /**
     * Use this method to filter OUT the routes you don't want to include in the smoke test.
     */
    private function routeFilter(Route $route): bool
    {
        return
            (\in_array('GET', $route->getMethods(), true) || 0 === \count($route->getMethods()))
            && !str_contains($route->getPath(), '{') // Route with parameters are not testable in basic smoke test
            && !str_starts_with($route->getPath(), '/logout') // Don't logout
            && !str_starts_with($route->getPath(), '/debug') // Don't test debug routes
            && !str_starts_with($route->getPath(), '/admin') // Don't test admin routes
        ;
    }

    /**
     * @param array<mixed> $options
     * @param array<mixed> $server
     * @param class-string $className
     */
    private static function createClientLoggedAs(?string $email, array $options = [], array $server = [], string $className = User::class): KernelBrowser
    {
        $client = self::createClient($options, $server);
        if (null === $email) {
            return $client;
        }

        /** @var User $user */
        /** @phpstan-ignore-next-line */
        $user = $client->getContainer()->get('doctrine')->getRepository($className)->findOneBy(['username' => $email]);

        self::assertInstanceOf($className, $user);

        $client->loginUser($user);

        return $client;
    }
}
