<?php declare(strict_types=1);

namespace TaxApiDpd\WordPress;

use TaxApiDpd\Core\DpdNoticeMailParser;
use Taxmod\Core\Import\Mail\OrderMailParser;

/**
 * The boundary: registers the DPD notice reader with Taxmod Mail (filter `taxmail_parsers`) and a tab on the
 * modeller's Configuration page whose part «Mail» names what it reads (D-1006, D-1007).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class Plugin
{
    public const VERSION = '0.0.1';
    public const TAB     = 'dpd';

    public static function boot(): void
    {
        add_action('plugins_loaded', static function (): void {
            load_plugin_textdomain('taxapidpd', false, dirname(plugin_basename(dirname(__DIR__, 2) . '/wp-tax-api-dpd.php')) . '/languages');

            // The contract lives in the modeller; without it there is nothing to register with.
            if (! interface_exists(OrderMailParser::class)) {
                add_action('admin_notices', static fn () => print '<div class="notice notice-warning"><p><strong>Taxmod DPD:</strong> '
                    . esc_html__('needs the Taxonomy Modeller 0.1.89 or later and Taxmod Mail 0.6.0 or later.', 'taxapidpd') . '</p></div>');

                return;
            }

            $reader = new DpdNoticeMailParser(self::VERSION);
            add_filter('taxmail_parsers', static fn (array $parsers): array => [...$parsers, $reader]);
            add_filter('taxmod_config_tabs', static fn (array $tabs): array => $tabs + [self::TAB => [
                'title'   => 'DPD',
                'version' => self::VERSION,
                'reader'  => $reader,
                'guide'   => self::guide(...),
                'render'  => static fn (): string => '',
                'save'    => static function (array $post): void {
                },
            ]], 30);
        });
    }

    /** The box «How to connect» beside the tab (D-963). */
    public static function guide(): string
    {
        return '<ol><li>' . esc_html__('Let DPD send its parcel notices to the mailbox of Taxmod Mail (or forward them there).', 'taxapidpd') . '</li><li>'
            . esc_html__('Keep this module active: each notice goes to the order with the same parcel number; it never creates an order.', 'taxapidpd') . '</li></ol><p>'
            . esc_html__('DPD\'s tracking API needs a business registration, so the mails are the source for now.', 'taxapidpd') . '</p>';
    }
}
