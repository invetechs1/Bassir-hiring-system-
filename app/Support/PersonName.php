<?php

namespace App\Support;

/**
 * Decides whether a piece of text is a person's name. Shared by the CV parser (first lines of a CV)
 * and the web-sourcing results (search-result titles, LinkedIn headlines, CV file names) so both
 * reject headings like "Curriculum Vitae", job titles and place names the same way.
 */
final class PersonName
{
    private const NOT_A_NAME = 'curriculum|vitae|resume|résumé|cv|profile|summary|objective|contact|personal|details|information|'
        .'experience|education|skills|engineer|engineering|manager|developer|architect|designer|analyst|accountant|specialist|'
        .'coordinator|supervisor|consultant|director|officer|technician|surveyor|planner|saudi|arabia|riyadh|jeddah|dammam|'
        .'date|birth|nationality|mobile|phone|email|address|page|declaration|reference|references|career|professional|senior|'
        .'junior|lead|company|ltd|llc|inc|group|university|college|institute|school|project|projects|construction|'
        .'bachelor|master|degree|diploma|certificate|certification|languages|language|hobbies|interests|achievements|training|courses|'
        .'software|computer|tools|strengths|about|work|history|employment|qualification|qualifications|technical|key|core|competencies|'
        .'linkedin|download|view|free|pdf|docx|doc|template|sample|example|job|jobs|hiring|vacancy|team|member|public|private|'
        .'kingdom|gulf|middle|east|uae|qatar|kuwait|bahrain|oman|egypt|jordan|pakistan|india|philippines|lebanon|'
        .'السيرة|الذاتية|مهندس';

    private const PARTICLES = ['bin', 'ibn', 'al', 'el', 'abu', 'abd', 'bint', 'van', 'de', 'der', 'von', 'da', 'di', 'ben'];

    public static function looksLikeName(string $text): bool
    {
        $text = trim($text, " \t|,-–•·*");
        $length = mb_strlen($text);
        if ($length < 5 || $length > 50) {
            return false;
        }
        if (preg_match('/[@\d\/\\\\:;()\[\]{}<>]|https?|www\./iu', $text)) {
            return false;
        }
        if (preg_match('/(?<![\p{L}])(?:'.self::NOT_A_NAME.')(?![\p{L}])/iu', $text)) {
            return false;
        }
        $words = preg_split('/\s+/u', $text) ?: [];
        if (count($words) < 2 || count($words) > 4) {
            return false;
        }
        foreach ($words as $word) {
            if (! preg_match('/^\p{L}[\p{L}\'’.\-]*$/u', $word)) {
                return false;
            }
            $isArabic = preg_match('/\p{Arabic}/u', $word) === 1;
            $isParticle = in_array(mb_strtolower($word), self::PARTICLES, true);
            if (! $isArabic && ! $isParticle && ! preg_match('/^\p{Lu}/u', $word)) {
                return false;
            }
        }

        return true;
    }

    /** "JOHN SMITH" -> "John Smith"; trims decoration characters. */
    public static function tidy(string $name): string
    {
        $name = trim($name, " \t|,-–•·*");
        if (mb_strtoupper($name) === $name && ! preg_match('/\p{Arabic}/u', $name)) {
            $name = mb_convert_case($name, MB_CASE_TITLE);
        }

        return $name;
    }

    /** "Ahmed_Al-Harbi_CV_2024.pdf" -> "Ahmed Al-Harbi" (null when the file name holds no real name). */
    public static function fromFileName(string $path): ?string
    {
        $base = pathinfo(rawurldecode($path), PATHINFO_FILENAME);
        $base = preg_replace('/[_.+]+/', ' ', $base) ?? $base;
        $base = preg_replace('/\b(cv|resume|résumé|curriculum|vitae|updated|latest|final|new|copy|pdf|docx?|v\d+)\b/iu', ' ', $base) ?? $base;
        $base = trim(preg_replace('/[\d\s]+$/u', '', preg_replace('/\s+/', ' ', $base) ?? $base) ?? $base, " -");

        $base = self::tidy($base);

        return self::looksLikeName($base) ? $base : null;
    }
}
