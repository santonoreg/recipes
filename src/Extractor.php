<?php
declare(strict_types=1);

namespace App;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/** Εξαγωγή δομημένης συνταγής με το Claude (κείμενο post + ομιλία + καρέ). */
final class Extractor
{
    private const SYSTEM = <<<'TXT'
        Είσαι βοηθός που μετατρέπει βίντεο μαγειρικής από τα social media σε καθαρές, εκτελέσιμες συνταγές.

        Θα λάβεις έως τρεις πηγές για το ίδιο post:
        1. Το κείμενο του post (συχνά εκεί είναι τα υλικά με ακριβείς ποσότητες).
        2. Την απομαγνητοφώνηση της ομιλίας με χρονοσημάνσεις [mm:ss] (αυτόματη, μπορεί να έχει λάθη λέξεων).
        3. Καρέ από το βίντεο με χρονοσήμανση (υλικά, συσκευασίες, ζυγαριά, κείμενα στην οθόνη, υφή, τελικό πιάτο).
           Αν τα καρέ είναι στιγμιότυπα οθόνης, αγνόησε ό,τι δεν αφορά τη συνταγή (μενού, σχόλια, διαφημίσεις).

        Κανόνες:
        - Συνδύασε τις πηγές. Για ποσότητες προτίμησε το κείμενο, μετά την ομιλία, μετά ό,τι φαίνεται στην εικόνα.
        - Μην επινοείς ποσότητες. Αν μια ποσότητα δεν αναφέρεται, άφησέ την κενή· μπορείς να δώσεις λογική εκτίμηση
          μόνο σημειώνοντας source="εκτίμηση".
        - Διόρθωσε προφανή λάθη απομαγνητοφώνησης από τα συμφραζόμενα (π.χ. ονόματα υλικών).
        - Τα βήματα να είναι σύντομα, σε προστακτική, με σειρά, θερμοκρασίες φούρνου και χρόνους όπου ειπώθηκαν.
        - Κράτα τις μονάδες του δημιουργού· αν είναι φλιτζάνια/oz, πρόσθεσε στο note την αντιστοιχία σε γρ./ml.
        - Ό,τι είναι αβέβαιο ή αντικρουόμενο γράψ' το στο uncertainties.
        - Χρόνους/μερίδες που δεν αναφέρονται άφησέ τα κενά.
        - Στο cover_frame βάλε τον αριθμό του καρέ που δείχνει καλύτερα το έτοιμο πιάτο (θα γίνει η φωτογραφία
          της συνταγής)· -1 αν δεν υπάρχουν καρέ.
        - Οι πηγές μπορεί να είναι σε οποιαδήποτε γλώσσα, ακόμα και διαφορετική η καθεμία.
        TXT;

    private static function languageRules(): string
    {
        $lang = Config::get('OUTPUT_LANGUAGE', 'Ελληνικά');
        if (preg_match('/^(ελλην|greek|el$)/iu', $lang)) {
            return "- Γράψε όλη τη συνταγή, μαζί με τον τίτλο και τις μονάδες, πάντα στα ελληνικά, όποια κι αν είναι η γλώσσα των πηγών.\n"
                . "  Μετάφρασε φυσικά, όπως θα το έγραφε ένα ελληνικό βιβλίο μαγειρικής.\n"
                . '- Υλικά χωρίς ελληνική ονομασία γράψ\' τα με ελληνικούς ή λατινικούς χαρακτήρες, ποτέ σε άλλο αλφάβητο.';
        }
        return "- Γράψε όλη τη συνταγή, μαζί με τον τίτλο και τις μονάδες, πάντα στη γλώσσα «{$lang}», όποια κι αν είναι η γλώσσα των πηγών.\n"
            . '  Μετάφρασε φυσικά, όπως θα το έγραφε ένα βιβλίο μαγειρικής σε αυτή τη γλώσσα. '
            . 'Κράτα το πεδίο source με τις ελληνικές τιμές του σχήματος.';
    }

    /** @return array<string,mixed> JSON schema της συνταγής */
    public static function schema(): array
    {
        $str = fn (string $d) => ['type' => 'string', 'description' => $d];
        $list = fn (string $d) => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => $d];
        $obj = fn (array $props) => [
            'type' => 'object',
            'properties' => $props,
            'required' => array_keys($props),
            'additionalProperties' => false,
        ];
        $ingredient = $obj([
            'name' => $str('Όνομα υλικού'),
            'quantity' => $str("Ποσότητα όπως ειπώθηκε/γράφτηκε (π.χ. '2', '1/2', '200'). Κενό αν δεν αναφέρθηκε."),
            'unit' => $str('Μονάδα (γρ., ml, κ.σ., κ.γ., φλιτζάνι, τεμ.). Κενό αν δεν υπάρχει.'),
            'note' => $str("Διευκρίνιση (π.χ. 'ψιλοκομμένο', 'σε θερμοκρασία δωματίου'). Κενό αν δεν χρειάζεται."),
            'source' => [
                'type' => 'string',
                'enum' => ['κείμενο', 'ομιλία', 'εικόνα', 'εκτίμηση'],
                'description' => "Από πού προέκυψε η ποσότητα· 'εκτίμηση' μόνο αν δεν αναφέρθηκε πουθενά",
            ],
        ]);
        $group = $obj([
            'title' => $str("Τίτλος ομάδας (π.χ. 'Για τη ζύμη'). Κενό αν υπάρχει μόνο μία ομάδα."),
            'items' => ['type' => 'array', 'items' => $ingredient],
        ]);
        return $obj([
            'is_recipe' => ['type' => 'boolean', 'description' => 'False αν το περιεχόμενο δεν περιέχει συνταγή'],
            'title' => $str('Τίτλος της συνταγής'),
            'description' => $str('1–2 προτάσεις για το φαγητό'),
            'servings' => $str('Μερίδες'),
            'prep_time' => $str('Χρόνος προετοιμασίας'),
            'cook_time' => $str('Χρόνος μαγειρέματος'),
            'total_time' => $str('Συνολικός χρόνος'),
            'ingredient_groups' => ['type' => 'array', 'items' => $group],
            'steps' => $list('Βήματα εκτέλεσης, ένα ανά στοιχείο, με θερμοκρασίες και χρόνους'),
            'tips' => $list('Συμβουλές/κόλπα που ανέφερε ο δημιουργός'),
            'uncertainties' => $list('Τι ήταν ασαφές ή αντικρουόμενο ανάμεσα στις πηγές'),
            'cover_frame' => ['type' => 'integer', 'description' => 'Αριθμός του καρέ με το έτοιμο πιάτο, ή -1'],
        ]);
    }

    /**
     * @param list<array{0:float,1:string}> $frames
     * @param callable(string):void $log
     * @return array<string,mixed>
     */
    public static function extract(string $postText, string $transcript, array $frames, callable $log): array
    {
        $apiKey = Config::get('ANTHROPIC_API_KEY');
        if ($apiKey === '') {
            throw new \RuntimeException('Λείπει το ANTHROPIC_API_KEY — βάλ\' το στο αρχείο .env.');
        }
        $model = Config::get('CLAUDE_MODEL', 'claude-opus-5-5');
        $effort = Config::get('CLAUDE_EFFORT', 'medium');

        $content = [
            ['type' => 'text', 'text' => "## Κείμενο post\n" . (trim($postText) ?: '(δεν υπάρχει)')],
            ['type' => 'text', 'text' => "## Απομαγνητοφώνηση\n" . (trim($transcript) ?: '(δεν υπάρχει ομιλία)')],
        ];
        if ($frames) {
            $content[] = ['type' => 'text', 'text' => '## Καρέ από το βίντεο (' . count($frames) . ')'];
            foreach ($frames as $i => [$sec, $path]) {
                $content[] = ['type' => 'text', 'text' => "Καρέ #$i — " . Media::fmtTs($sec)];
                $content[] = ['type' => 'image', 'source' => [
                    'type' => 'base64',
                    'mediaType' => 'image/jpeg',
                    'data' => base64_encode((string) file_get_contents($path)),
                ]];
            }
        }
        $content[] = ['type' => 'text', 'text' => 'Βγάλε τη συνταγή.'];

        $log("Ανάλυση με $model (" . count($frames) . ' καρέ)…');
        $client = new Client(apiKey: $apiKey);
        try {
            $message = $client->beta->messages->create(
                maxTokens: 16000,
                messages: [['role' => 'user', 'content' => $content]],
                model: $model,
                system: self::SYSTEM . "\n" . self::languageRules(),
                outputConfig: [
                    'effort' => $effort,
                    'format' => ['type' => 'json_schema', 'schema' => self::schema()],
                ],
                // αν κάποιο φίλτρο ασφαλείας απορρίψει το αίτημα, το API το ξανατρέχει σε άλλο μοντέλο
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
            );
        } catch (AuthenticationException $e) {
            throw new \RuntimeException('Το ANTHROPIC_API_KEY δεν είναι έγκυρο.', 0, $e);
        } catch (RateLimitException $e) {
            throw new \RuntimeException('Όριο αιτημάτων του Claude API — δοκίμασε ξανά σε λίγο.', 0, $e);
        } catch (APIStatusException $e) {
            throw new \RuntimeException('Σφάλμα του Claude API: ' . $e->getMessage(), 0, $e);
        } catch (APIConnectionException $e) {
            throw new \RuntimeException('Δεν υπάρχει σύνδεση με το Claude API: ' . $e->getMessage(), 0, $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new \RuntimeException('Το μοντέλο αρνήθηκε το αίτημα: ' . ($message->stopDetails?->explanation ?? ''));
        }
        if ($message->stopReason === 'max_tokens') {
            throw new \RuntimeException('Η απάντηση κόπηκε (max_tokens).');
        }
        $recipe = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $recipe = json_decode($block->text, true);
                break;
            }
        }
        if (!is_array($recipe) || !isset($recipe['title'], $recipe['ingredient_groups'], $recipe['steps'])) {
            throw new \RuntimeException('Το μοντέλο δεν επέστρεψε έγκυρη συνταγή.');
        }
        $log(sprintf('  ✓ tokens: %d in / %d out', $message->usage->inputTokens, $message->usage->outputTokens));
        return $recipe;
    }
}
