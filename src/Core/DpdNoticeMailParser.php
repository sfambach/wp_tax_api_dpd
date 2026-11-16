<?php declare(strict_types=1);

namespace TaxApiDpd\Core;

use Taxmod\Core\Import\Mail\MailMessage;
use Taxmod\Core\Import\Mail\ParcelNoticeMailParser;
use Taxmod\Core\Import\ProposalKind;

/**
 * DPD's Predict notices («Bald ist Ihr DPD Paket da», «Zwischen … Uhr kommt Ihr DPD Paket», «Ihr Paket ist da!»,
 * «Die Abstellung Ihres DPD Pakets ist erfolgt»): no shop order, only the parcel number. The modeller gives them to
 * the order that carries that number, whatever shop it is from (D-1020). DPD's tracking API needs a business
 * registration, so the mail is the source.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class DpdNoticeMailParser extends ParcelNoticeMailParser
{
    private const DOMAINS = ['dpd.de', 'dpd.com'];

    public function __construct(string $version)
    {
        parent::__construct('dpd', 'DPD', self::DOMAINS, $version);
    }

    /**
     * The subject names the step in every layout seen since 2020. Announced and time window are both «on the way»;
     * delivered and set down at the agreed place are both «delivered».
     */
    protected function kind(MailMessage $mail): ProposalKind
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
    protected function parcelNumber(MailMessage $mail): ?string
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
