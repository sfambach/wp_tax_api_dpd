<?php declare(strict_types=1);

namespace TaxApiDpd\Tests\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TaxApiDpd\Core\DpdNoticeMailParser;
use Taxmod\Core\Import\Mail\MimeParser;

/**
 * The fixtures are real DPD notices of 2020–2026 with names, addresses and numbers replaced:
 * d1/d2 the layout until 2021-01, d3–d6 the layout since 2021-02.
 */
final class DpdNoticeMailParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function read(string $fixture): array
    {
        $parser = new DpdNoticeMailParser('0.0.1');
        $mail = (new MimeParser())->parse(1, (string) file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.eml'));
        self::assertTrue($parser->recognises($mail), 'not recognised: ' . $fixture);

        return $parser->parse($mail)->toArray();
    }

    public function testADeliveryGoesToTheOrderWithItsParcelNumber(): void
    {
        $notice = self::read('d5');

        self::assertSame(['DPD', 'sendung:01234567890005', 'delivered', ['01234567890005']], [$notice['platform'], $notice['order_number'], $notice['kind'], $notice['tracking']]);
        self::assertTrue($notice['number_missing']);
    }

    public function testAParcelSetDownAtTheAgreedPlaceIsDelivered(): void
    {
        self::assertSame(['delivered', ['01234567890006']], [self::read('d6')['kind'], self::read('d6')['tracking']]);
    }

    /** @return array<string, array{string, string}> */
    public static function onTheWay(): array
    {
        return [
            'announced, old layout'   => ['d1', '01234567890001'],
            'time window, old layout' => ['d2', '01234567890002'],
            'announced, new layout'   => ['d3', '01234567890003'],
            'time window, new layout' => ['d4', '01234567890004'],
        ];
    }

    #[DataProvider('onTheWay')]
    public function testAnnouncementAndTimeWindowAreShipped(string $fixture, string $number): void
    {
        $notice = self::read($fixture);

        self::assertSame(['shipped', [$number]], [$notice['kind'], $notice['tracking']]);
    }

    public function testTheTrackingLinkGivesTheNumberWhenTheTextDoesNot(): void
    {
        $mail = (new MimeParser())->parse(1, "From: DPD <noreply@service.dpd.de>\r\nSubject: Ihr Paket ist da!\r\nContent-Type: text/html; charset=utf-8\r\n\r\n"
            . '<p>Ihr Paket ist da!</p><a href="https://my.dpd.de/redirect.aspx?action=2&parcelno=B2C0123456789000720261101">Paket</a>');

        self::assertSame(['01234567890007'], (new DpdNoticeMailParser('0.0.1'))->parse($mail)->toArray()['tracking']);
    }

    public function testAMailWithoutStatusBringsNothing(): void
    {
        $mail = (new MimeParser())->parse(1, "From: DPD <newsletter@dpd.de>\r\nSubject: Neuigkeiten von DPD\r\n\r\nIhre Sendung 01234567890008 von X");

        self::assertSame('no_receipt', (new DpdNoticeMailParser('0.0.1'))->parse($mail)->toArray()['kind']);
    }

    public function testOtherSendersAreNotDpd(): void
    {
        $mail = (new MimeParser())->parse(1, "From: x@dhl.de\r\nSubject: Ihr Paket ist da!\r\n\r\nx");
        self::assertFalse((new DpdNoticeMailParser('0.0.1'))->recognises($mail));
    }
}
