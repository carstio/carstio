<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\User;
use App\Enum\SupportedLocale;
use App\Security\PasswordReset\PasswordResetLinks;
use App\Service\Pdf\CompanyProfileProvider;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Builds the "choose a new password" {@see Email}, in the language of the
 * person it is for.
 */
class PasswordResetEmailFactory
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly CompanyProfileProvider $companyProvider,
    ) {
    }

    public function build(User $user, string $resetUrl, Address $from): Email
    {
        return $this->localeSwitcher->runWithLocale(
            $this->localeFor($user),
            fn (): Email => $this->compose($user, $resetUrl, $from),
        );
    }

    private function compose(User $user, string $resetUrl, Address $from): Email
    {
        $company = trim((string) $this->companyProvider->getCompany()->companyName);
        if ($company === '') {
            $company = $this->translator->trans('app.name');
        }

        $subject = $this->translator->trans('email.password_reset.subject', ['%company%' => $company]);
        $context = [
            'subject' => $subject,
            'name' => (string) $user->getFullName(),
            'company' => $company,
            'reset_url' => $resetUrl,
            'minutes' => PasswordResetLinks::LIFETIME_MINUTES,
        ];

        return (new Email())
            ->from($from)
            ->to(new Address((string) $user->getEmail(), (string) $user->getFullName()))
            ->subject($subject)
            ->text($this->twig->render('emails/password_reset.txt.twig', $context))
            ->html($this->twig->render('emails/password_reset.html.twig', $context));
    }

    /**
     * The language the user works in, or the one of the page the request
     * came from when they never chose one.
     */
    private function localeFor(User $user): string
    {
        $preferred = $user->getPreferredLocale();

        return SupportedLocale::isSupported($preferred) ? (string) $preferred : $this->localeSwitcher->getLocale();
    }
}
