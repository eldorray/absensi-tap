<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Isi berformat (rich text) dari editor pengumuman.
 *
 * HTML dari browser tidak pernah dipercaya: setiap isi dibersihkan ke daftar
 * tag yang bisa dibuat toolbar editor, dan frontend baru boleh merendernya
 * dengan {@html} karena lewat sini dulu. Tambah tag di toolbar = tambah di sini.
 */
final class IsiKaya
{
    public static function bersihkan(string $html): string
    {
        static $sanitizer = null;

        $sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('em')
                ->allowElement('u')
                ->allowElement('s')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('blockquote')
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->forceAttribute('a', 'target', '_blank')
                ->withMaxInputLength(50_000)
        );

        return trim($sanitizer->sanitize($html));
    }

    /**
     * Teks polos untuk pratinjau dan validasi "isi tidak kosong".
     */
    public static function teks(string $html): string
    {
        // Batas blok diberi spasi dulu supaya "<p>a</p><p>b</p>" tidak jadi "ab".
        $berjarak = preg_replace('/<\/(p|li|h2|h3|blockquote)>|<br\s*\/?>/i', ' ', $html) ?? $html;

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($berjarak), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * Teks polos lama (sebelum editor) jadi HTML: paragraf dipisah baris
     * kosong, baris tunggal jadi <br>. Karakter HTML di-escape.
     */
    public static function dariTeksPolos(string $teks): string
    {
        $paragraf = preg_split('/\R{2,}/u', trim($teks)) ?: [];

        return implode('', array_map(
            fn (string $p): string => '<p>'.nl2br(e($p), false).'</p>',
            array_filter($paragraf, fn (string $p): bool => trim($p) !== ''),
        ));
    }
}
