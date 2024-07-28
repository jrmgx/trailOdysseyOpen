<?php

namespace App\Twig;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class HelperExtension extends AbstractExtension
{
    private const COLORS = [
        'f30000',
        '000fd1',
        'd100b1',
        'd19400',
        '7d7d7d',
        'ff81f5',
        'fff961',
        '000000',
        '00f300',
        'd10000',
        '00d1fd',
        'b1d100',
        '00d194',
        '7d7dff',
        '81f5ff',
    ];

    public function __construct(
        private readonly IntlExtension $intlExtension,
        private readonly Security $security,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('is_instanceof', $this->instanceof(...)),
            new TwigFilter(
                'format_datetime_app',
                $this->formatDatetimeApp(...),
                ['needs_environment' => true]
            ),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('color_from_index', $this->colorFromIndex(...)),
        ];
    }

    public function formatDatetimeApp(
        Environment $env,
        \DateTimeInterface|string|null $date,
        string $dateFormat = 'full',
        string $timeFormat = 'short',
    ): string {
        $currentYear = (new \DateTimeImmutable())->format('Y');
        /** @var ?User $user */
        $user = $this->security->getUser();
        $dateTime = $this->intlExtension
            ->formatDateTime($env, $date, $dateFormat, $timeFormat, timezone: $user?->getTimezone() ?? 'UTC')
        ;
        /** @var array<string> $parts */
        $parts = explode(" $currentYear ", $dateTime);
        if (1 === \count($parts)) {
            return $dateTime;
        }

        return trim($parts[0], ',') . ' ' . trim($parts[1]);
    }

    public function instanceof(?object $object, string $class): bool
    {
        return $object instanceof $class;
    }

    public function colorFromIndex(int $index): string
    {
        return self::COLORS[$index % \count(self::COLORS)];
    }
}
