<?php declare(strict_types=1);

namespace TaxApiDpd\Core;

use Taxmod\Core\Import\Mail\MailMessage;
use Taxmod\Core\Import\Mail\OrderMailParser;
use Taxmod\Core\Import\OrderProposal;
use Taxmod\Core\Import\ProposalKind;

/**
 * DPD's Predict notices («Bald ist Ihr DPD Paket da», «Zwischen … Uhr kommt Ihr DPD Paket», «Ihr Paket ist da!»,
 * «Die Abstellung Ihres DPD Pakets ist erfolgt»): no shop order, only the parcel number. The modeller gives them to
 * the order that carries that number, whatever shop it is from (D-1020). DPD's tracking API needs a business
 * registration, so the mail is the source.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class DpdNoticeMailParser implements OrderMailParser
{
    private const DOMAINS = ['dpd.de', 'dpd.com'];

    public function __construct(private readonly string $version)
    {
    }

    public function key(): string
    {
        return 'dpd';
    }

    public function shopName(): string
    {
        return 'DPD';
    }

    public function recognises(MailMessage $mail): bool
    {
        $domain = $mail->senderDomain();

        return array_filter(self::DOMAINS, static fn (string $dpd): bool => $domain === $dpd || str_ends_with($domain, '.' . $dpd)) !== [];
    }

    public function parse(MailMessage $mail): OrderProposal
    {
        $number = $this->parcelNumber($mail);
        $kind = $this->kind($mail);

        if ($kind === ProposalKind::NoReceipt || $number === null) {
            return new OrderProposal($this->shopName(), '', ProposalKind::NoReceipt);
        }

        return new OrderProposal($this->shopName(), 'sendung:' . $number, $kind, trackingNumbers: [$number], numberMissing: true);
    }

    public function kinds(): array
    {
        return [ProposalKind::Shipped, ProposalKind::Delivered];
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * The subject names the step in every layout seen since 2020. Announced and time window are both «on the way»;
     * delivered and set down at the agreed place are both «delivered».
     */
    private function kind(MailMessage $mail): ProposalKind
    {
        $subject = mb_strtolower($mail->subject);

        return match (true) {
            preg_match('/ist da!|zugestellt|abstellung .*erfolgt|abgestellt/u', $subject) === 1 => ProposalKind::Delivered,
            preg_match('/bald ist ihr dpd paket da|kommt ihr dpd paket|unterwegs|zustellung/u', $subject) === 1 => ProposalKind::Shipped,
            default => ProposalKind::NoReceipt,
        };
    }

    /**
     * The 14-digit parcel number: «Ihre Sendung 0944… von …» (layout until 2021-01), the line after the shipper
     * below «Versender & Paketnummer:» (since 2021-02), or the tracking link, which wraps it between a three-character prefix (EXP, B2C) and the date.
     */
    private function parcelNumber(MailMessage $mail): ?string
    {
        $lines = $mail->lines();

        foreach ($lines as $k => $line) {
            if (preg_match('/Ihre Sendung (\d{14})\b/', $line, $found) === 1) {
                return $found[1];
            }

            if (str_contains($line, 'Paketnummer')) {
                foreach (array_slice($lines, $k, 4) as $next) {
                    if (preg_match('/(?<!\d)(\d{14})(?!\d)/', $next, $found) === 1) {
                        return $found[1];
                    }
                }
            }
        }

        return preg_match('/parcelno=(?:3D)?[A-Z0-9]{3}(\d{14})/', $mail->html, $found) === 1 ? $found[1] : null;
    }
}
