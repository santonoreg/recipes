<?php
declare(strict_types=1);

namespace App;

use Mpdf\Mpdf;

/** PDF συνταγής (μόνο κείμενο) με mPDF — οι γραμματοσειρές DejaVu του έχουν ελληνικά. */
final class Pdf
{
    /** @param array<string,mixed> $row γραμμή συνταγής από τη βάση */
    public static function render(array $row): string
    {
        $recipe = $row['recipe'];
        ob_start();
        require Config::root() . '/templates/pdf.php';
        $html = (string) ob_get_clean();

        $source = (string) $row['source_url'];
        $footerLeft = $source !== ''
            ? (mb_strlen($source) > 90 ? mb_substr($source, 0, 87) . '…' : $source)
            : 'Συνταγές · ' . date('d/m/Y');

        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_left' => 20, 'margin_right' => 20, 'margin_top' => 18, 'margin_bottom' => 22,
            'margin_footer' => 10,
            'default_font' => 'dejavusans',
            'tempDir' => Config::ensureDir(Config::dataDir() . '/tmp/mpdf'),
        ]);
        $mpdf->SetTitle((string) $row['title']);
        $mpdf->SetAuthor('Συνταγές');
        $mpdf->SetSubject((string) $row['description']);
        $mpdf->SetHTMLFooter(
            '<table width="100%" style="border-top:0.5pt solid #E8DCCD;font-size:7.5pt;color:#8A7E74;"><tr>'
            . '<td style="padding-top:4pt;">' . htmlspecialchars($footerLeft) . '</td>'
            . '<td style="padding-top:4pt;text-align:right;">{PAGENO}</td></tr></table>'
        );
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    public static function fileName(string $title): string
    {
        $name = trim((string) preg_replace('~[<>:"/\\\\|?*\x00-\x1f]~u', '', $title), ' .');
        return (mb_substr($name, 0, 80) ?: 'συνταγή') . '.pdf';
    }
}
