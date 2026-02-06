<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Readability Analysis
 *
 * Hungarian-optimized readability checks including:
 * - Flesch Reading Ease (Hungarian adaptation)
 * - Sentence length analysis
 * - Paragraph structure
 * - Passive voice detection
 * - Transition words
 * - Subheading distribution
 */
class RSEO_Readability {

    /**
     * Hungarian transition words
     */
    private static $transition_words = [
        // Additív
        'továbbá', 'emellett', 'ezen kívül', 'valamint', 'sőt', 'ráadásul', 'azonfelül', 'mi több',
        'hasonlóképpen', 'ugyanígy', 'szintén', 'is', 'nem csak', 'hanem', 'egyrészt', 'másrészt',
        // Ellentét
        'azonban', 'viszont', 'ellenben', 'mindazonáltal', 'ugyanakkor', 'ezzel szemben', 'de',
        'mégis', 'ennek ellenére', 'mindamellett', 'noha', 'bár', 'habár', 'jóllehet', 'ámbár',
        // Okozati
        'ezért', 'emiatt', 'következésképpen', 'ennek következtében', 'tehát', 'így', 'ennélfogva',
        'ebből kifolyólag', 'ennek eredményeként', 'mivel', 'mert', 'ugyanis', 'hiszen', 'minthogy',
        // Időbeli
        'először', 'másodszor', 'végül', 'aztán', 'ezután', 'majd', 'később', 'előbb', 'utóbb',
        'korábban', 'egyidejűleg', 'közben', 'miközben', 'mialatt', 'miután', 'mielőtt', 'amíg',
        // Példázó
        'például', 'mint például', 'nevezetesen', 'így például', 'többek között', 'úgymint',
        // Összegző
        'összefoglalva', 'összegezve', 'végeredményben', 'mindent összevetve', 'röviden',
        'egyszóval', 'konklúzióként', 'befejezésül', 'végezetül', 'mindent egybevetve',
        // Kiemelő
        'különösen', 'főleg', 'elsősorban', 'legfőképpen', 'mindenekelőtt', 'kiváltképpen',
        'fontos', 'lényeges', 'jelentős', 'kiemelendő', 'megjegyzendő', 'hangsúlyozandó',
    ];

    /**
     * Hungarian passive voice indicators
     */
    private static $passive_indicators = [
        // -va/-ve + van/volt/lesz
        'meg van', 'meg lett', 'meg lesz', 'el van', 'el lett', 'el lesz',
        'ki van', 'ki lett', 'ki lesz', 'be van', 'be lett', 'be lesz',
        'fel van', 'fel lett', 'fel lesz', 'le van', 'le lett', 'le lesz',
        // -ódik/-ődik (medio-passive)
        'íródik', 'íródott', 'készül', 'készült', 'történik', 'történt',
        'alakul', 'alakult', 'változik', 'változott', 'fejlődik', 'fejlődött',
        // -atik/-etik/-tatik/-tetik (archaic passive)
        'mondatik', 'neveztetik', 'tartatik', 'adatik', 'vétetik',
        // által + past participle
        'által', 'révén', 'útján', 'folytán',
    ];

    /**
     * Analyze content readability
     *
     * @param string $content The content to analyze
     * @param string $focus_keyword Optional focus keyword
     * @return array Analysis results
     */
    public static function analyze( $content, $focus_keyword = '' ) {
        $text = wp_strip_all_tags( $content );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

        $results = [
            'score'  => 0,
            'checks' => [],
        ];

        // Skip if too little content
        if ( mb_strlen( $text ) < 100 ) {
            return $results;
        }

        // Gather text metrics
        $metrics = self::get_text_metrics( $text );

        // Run checks
        $checks = [];
        $checks[] = self::check_flesch_reading_ease( $metrics );
        $checks[] = self::check_sentence_length( $metrics );
        $checks[] = self::check_paragraph_length( $content );
        $checks[] = self::check_passive_voice( $text, $metrics );
        $checks[] = self::check_transition_words( $text, $metrics );
        $checks[] = self::check_subheading_distribution( $content, $metrics );
        $checks[] = self::check_consecutive_sentences( $text );

        // Calculate total score
        $total_weight = 0;
        $earned_points = 0;

        foreach ( $checks as $check ) {
            $total_weight += $check['weight'];
            $pass_value = is_bool( $check['pass'] ) ? ( $check['pass'] ? 1.0 : 0.0 ) : floatval( $check['pass'] );
            $earned_points += $check['weight'] * $pass_value;
        }

        $results['score'] = $total_weight > 0 ? round( ( $earned_points / $total_weight ) * 100 ) : 0;
        $results['checks'] = $checks;
        $results['metrics'] = $metrics;

        return $results;
    }

    /**
     * Get text metrics
     */
    private static function get_text_metrics( $text ) {
        // Sentences
        $sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $sentences = array_filter( array_map( 'trim', $sentences ) );
        $sentence_count = count( $sentences );

        // Words
        $words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $word_count = count( $words );

        // Syllables (Hungarian approximation)
        $syllable_count = 0;
        foreach ( $words as $word ) {
            $syllable_count += self::count_syllables_hungarian( $word );
        }

        // Sentence lengths
        $sentence_lengths = [];
        foreach ( $sentences as $sentence ) {
            $sentence_lengths[] = str_word_count( $sentence );
        }

        return [
            'text'              => $text,
            'sentences'         => $sentences,
            'sentence_count'    => $sentence_count,
            'words'             => $words,
            'word_count'        => $word_count,
            'syllable_count'    => $syllable_count,
            'sentence_lengths'  => $sentence_lengths,
            'avg_sentence_len'  => $sentence_count > 0 ? $word_count / $sentence_count : 0,
            'avg_syllables'     => $word_count > 0 ? $syllable_count / $word_count : 0,
            'char_count'        => mb_strlen( $text ),
        ];
    }

    /**
     * Count syllables in Hungarian word
     * Hungarian syllables are primarily based on vowels
     */
    private static function count_syllables_hungarian( $word ) {
        $word = mb_strtolower( $word );
        // Hungarian vowels (including accented)
        $vowels = 'aáeéiíoóöőuúüű';
        $count = 0;

        for ( $i = 0; $i < mb_strlen( $word ); $i++ ) {
            $char = mb_substr( $word, $i, 1 );
            if ( mb_strpos( $vowels, $char ) !== false ) {
                $count++;
            }
        }

        return max( 1, $count );
    }

    /**
     * Check Flesch Reading Ease (Hungarian adaptation)
     * Formula: 206.835 - (1.015 × ASL) - (73.6 × ASW)
     * ASL = Average Sentence Length, ASW = Average Syllables per Word
     */
    private static function check_flesch_reading_ease( $metrics ) {
        if ( $metrics['sentence_count'] < 3 ) {
            return [
                'id'       => 'flesch_reading_ease',
                'weight'   => 5,
                'pass'     => 0.5,
                'message'  => 'Túl kevés mondat az olvashatóság méréséhez',
                'fix'      => 'Írj legalább 3 mondatot',
                'category' => 'readability',
            ];
        }

        $asl = $metrics['avg_sentence_len'];
        $asw = $metrics['avg_syllables'];

        // Hungarian-adapted Flesch (coefficients adjusted for Hungarian language)
        $flesch = 206.835 - ( 1.015 * $asl ) - ( 73.6 * $asw );
        $flesch = max( 0, min( 100, $flesch ) );

        $score_str = number_format( $flesch, 1 );

        if ( $flesch >= 60 ) {
            return [
                'id'       => 'flesch_reading_ease',
                'weight'   => 5,
                'pass'     => true,
                'message'  => "Kiváló olvashatóság (Flesch: {$score_str})",
                'category' => 'readability',
            ];
        }

        if ( $flesch >= 40 ) {
            return [
                'id'       => 'flesch_reading_ease',
                'weight'   => 5,
                'pass'     => 0.7,
                'message'  => "Megfelelő olvashatóság (Flesch: {$score_str})",
                'fix'      => 'Próbálj egyszerűbb mondatokat használni',
                'category' => 'readability',
            ];
        }

        return [
            'id'       => 'flesch_reading_ease',
            'weight'   => 5,
            'pass'     => 0.3,
            'message'  => "Nehezen olvasható szöveg (Flesch: {$score_str})",
            'fix'      => 'Rövidítsd a mondatokat és használj egyszerűbb szavakat',
            'category' => 'readability',
        ];
    }

    /**
     * Check sentence length
     * Ideal: < 20 words per sentence on average
     */
    private static function check_sentence_length( $metrics ) {
        $avg = $metrics['avg_sentence_len'];
        $long_sentences = 0;

        foreach ( $metrics['sentence_lengths'] as $len ) {
            if ( $len > 25 ) {
                $long_sentences++;
            }
        }

        $long_percent = $metrics['sentence_count'] > 0
            ? ( $long_sentences / $metrics['sentence_count'] ) * 100
            : 0;

        $avg_str = number_format( $avg, 1 );

        if ( $avg <= 20 && $long_percent <= 25 ) {
            return [
                'id'       => 'sentence_length',
                'weight'   => 4,
                'pass'     => true,
                'message'  => "Mondathossz optimális (átlag: {$avg_str} szó)",
                'category' => 'readability',
            ];
        }

        if ( $avg <= 25 ) {
            return [
                'id'       => 'sentence_length',
                'weight'   => 4,
                'pass'     => 0.6,
                'message'  => "Mondatok kissé hosszúak (átlag: {$avg_str} szó, {$long_sentences} túl hosszú)",
                'fix'      => 'Bontsd rövidebbre a 25 szónál hosszabb mondatokat',
                'category' => 'readability',
            ];
        }

        return [
            'id'       => 'sentence_length',
            'weight'   => 4,
            'pass'     => 0.3,
            'message'  => "Túl hosszú mondatok (átlag: {$avg_str} szó)",
            'fix'      => 'Az olvashatósághoz max. 20 szó/mondat az ideális',
            'category' => 'readability',
        ];
    }

    /**
     * Check paragraph length
     * Ideal: max 150 words per paragraph
     */
    private static function check_paragraph_length( $content ) {
        // Split by paragraph tags or double newlines
        $paragraphs = preg_split( '/<\/p>|<br\s*\/?>\s*<br\s*\/?>|\n\n+/', $content );
        $paragraphs = array_filter( array_map( function( $p ) {
            return wp_strip_all_tags( trim( $p ) );
        }, $paragraphs ) );

        if ( count( $paragraphs ) < 2 ) {
            return [
                'id'       => 'paragraph_length',
                'weight'   => 4,
                'pass'     => 0.5,
                'message'  => 'Kevés bekezdés – tagold jobban a szöveget',
                'fix'      => 'Oszd fel a szöveget több bekezdésre (max. 150 szó/bekezdés)',
                'category' => 'readability',
            ];
        }

        $long_paragraphs = 0;
        foreach ( $paragraphs as $p ) {
            if ( str_word_count( $p ) > 150 ) {
                $long_paragraphs++;
            }
        }

        if ( $long_paragraphs === 0 ) {
            return [
                'id'       => 'paragraph_length',
                'weight'   => 4,
                'pass'     => true,
                'message'  => 'Bekezdések jól tagoltak (' . count( $paragraphs ) . ' db)',
                'category' => 'readability',
            ];
        }

        $percent = ( $long_paragraphs / count( $paragraphs ) ) * 100;
        if ( $percent <= 25 ) {
            return [
                'id'       => 'paragraph_length',
                'weight'   => 4,
                'pass'     => 0.7,
                'message'  => "{$long_paragraphs} bekezdés túl hosszú",
                'fix'      => 'Bontsd rövidebbre a 150 szónál hosszabb bekezdéseket',
                'category' => 'readability',
            ];
        }

        return [
            'id'       => 'paragraph_length',
            'weight'   => 4,
            'pass'     => 0.4,
            'message'  => "Sok túl hosszú bekezdés ({$long_paragraphs} db)",
            'fix'      => 'A bekezdések max. 150 szó hosszúak legyenek',
            'category' => 'readability',
        ];
    }

    /**
     * Check passive voice usage
     * Ideal: < 10% passive sentences
     */
    private static function check_passive_voice( $text, $metrics ) {
        $text_lower = mb_strtolower( $text );
        $passive_count = 0;

        foreach ( self::$passive_indicators as $indicator ) {
            $passive_count += mb_substr_count( $text_lower, $indicator );
        }

        // Approximate passive sentences
        $passive_percent = $metrics['sentence_count'] > 0
            ? min( 100, ( $passive_count / $metrics['sentence_count'] ) * 100 )
            : 0;

        $percent_str = number_format( $passive_percent, 0 );

        if ( $passive_percent <= 10 ) {
            return [
                'id'       => 'passive_voice',
                'weight'   => 4,
                'pass'     => true,
                'message'  => "Kevés passzív szerkezet ({$percent_str}%)",
                'category' => 'readability',
            ];
        }

        if ( $passive_percent <= 20 ) {
            return [
                'id'       => 'passive_voice',
                'weight'   => 4,
                'pass'     => 0.6,
                'message'  => "Mérsékelt passzív használat ({$percent_str}%)",
                'fix'      => 'Próbálj több aktív mondatot használni',
                'category' => 'readability',
            ];
        }

        return [
            'id'       => 'passive_voice',
            'weight'   => 4,
            'pass'     => 0.3,
            'message'  => "Sok passzív szerkezet ({$percent_str}%)",
            'fix'      => 'Alakítsd át a passzív mondatokat aktívvá',
            'category' => 'readability',
        ];
    }

    /**
     * Check transition words usage
     * Ideal: > 30% of sentences start with transition words
     */
    private static function check_transition_words( $text, $metrics ) {
        $text_lower = mb_strtolower( $text );
        $transition_count = 0;

        foreach ( self::$transition_words as $word ) {
            $transition_count += mb_substr_count( $text_lower, mb_strtolower( $word ) );
        }

        $percent = $metrics['sentence_count'] > 0
            ? min( 100, ( $transition_count / $metrics['sentence_count'] ) * 100 )
            : 0;

        $percent_str = number_format( $percent, 0 );

        if ( $percent >= 30 ) {
            return [
                'id'       => 'transition_words',
                'weight'   => 3,
                'pass'     => true,
                'message'  => "Jó kötőszó használat ({$percent_str}%)",
                'category' => 'readability',
            ];
        }

        if ( $percent >= 20 ) {
            return [
                'id'       => 'transition_words',
                'weight'   => 3,
                'pass'     => 0.6,
                'message'  => "Mérsékelt kötőszó használat ({$percent_str}%)",
                'fix'      => 'Használj több kötőszót (pl. azonban, ezért, továbbá)',
                'category' => 'readability',
            ];
        }

        return [
            'id'       => 'transition_words',
            'weight'   => 3,
            'pass'     => 0.3,
            'message'  => "Kevés kötőszó ({$percent_str}%)",
            'fix'      => 'A kötőszavak segítenek a szöveg folyékonyságában',
            'category' => 'readability',
        ];
    }

    /**
     * Check subheading distribution
     * Ideal: subheading every 300 words
     */
    private static function check_subheading_distribution( $content, $metrics ) {
        preg_match_all( '/<h[2-6][^>]*>/i', $content, $headings );
        $heading_count = count( $headings[0] );

        if ( $metrics['word_count'] < 300 ) {
            return [
                'id'       => 'subheading_distribution',
                'weight'   => 4,
                'pass'     => true,
                'message'  => 'Rövid tartalom – alcím nem szükséges',
                'category' => 'readability',
            ];
        }

        $ideal_headings = floor( $metrics['word_count'] / 300 );
        $words_per_heading = $heading_count > 0
            ? $metrics['word_count'] / $heading_count
            : $metrics['word_count'];

        if ( $heading_count >= $ideal_headings && $words_per_heading <= 350 ) {
            return [
                'id'       => 'subheading_distribution',
                'weight'   => 4,
                'pass'     => true,
                'message'  => "Jó alcím eloszlás ({$heading_count} alcím)",
                'category' => 'readability',
            ];
        }

        if ( $heading_count > 0 && $words_per_heading <= 500 ) {
            return [
                'id'       => 'subheading_distribution',
                'weight'   => 4,
                'pass'     => 0.6,
                'message'  => "Lehetne több alcím ({$heading_count} db, ~" . round( $words_per_heading ) . " szó/alcím)",
                'fix'      => 'Adj hozzá alcímeket 300 szavanként',
                'category' => 'readability',
            ];
        }

        return [
            'id'       => 'subheading_distribution',
            'weight'   => 4,
            'pass'     => 0.3,
            'message'  => "Kevés vagy nincs alcím ({$heading_count} db)",
            'fix'      => 'Tagold a szöveget H2/H3 alcímekkel (300 szavanként)',
            'category' => 'readability',
        ];
    }

    /**
     * Check for consecutive sentences starting with the same word
     */
    private static function check_consecutive_sentences( $text ) {
        $sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $sentences = array_filter( array_map( 'trim', $sentences ) );

        if ( count( $sentences ) < 3 ) {
            return [
                'id'       => 'consecutive_sentences',
                'weight'   => 3,
                'pass'     => true,
                'message'  => 'Túl kevés mondat az ismétlődés vizsgálatához',
                'category' => 'readability',
            ];
        }

        $consecutive_same = 0;
        $prev_first_word = '';

        foreach ( $sentences as $sentence ) {
            $words = preg_split( '/\s+/', $sentence );
            $first_word = mb_strtolower( $words[0] ?? '' );

            if ( $first_word && $first_word === $prev_first_word ) {
                $consecutive_same++;
            }

            $prev_first_word = $first_word;
        }

        if ( $consecutive_same === 0 ) {
            return [
                'id'       => 'consecutive_sentences',
                'weight'   => 3,
                'pass'     => true,
                'message'  => 'Változatos mondatkezdések',
                'category' => 'readability',
            ];
        }

        if ( $consecutive_same <= 2 ) {
            return [
                'id'       => 'consecutive_sentences',
                'weight'   => 3,
                'pass'     => 0.6,
                'message'  => "{$consecutive_same} egymás utáni mondat ugyanazzal a szóval kezdődik",
                'fix'      => 'Változtasd meg a mondatkezdéseket',
                'category' => 'readability',
            ];
        }

        return [
            'id'       => 'consecutive_sentences',
            'weight'   => 3,
            'pass'     => 0.3,
            'message'  => "Sok ismétlődő mondatkezdés ({$consecutive_same} db)",
            'fix'      => 'Kerüld az ugyanazzal a szóval kezdődő mondatokat',
            'category' => 'readability',
        ];
    }

    /**
     * Get readability grade label
     */
    public static function get_grade_label( $score ) {
        if ( $score >= 80 ) return 'Kiváló';
        if ( $score >= 60 ) return 'Jó';
        if ( $score >= 40 ) return 'Elfogadható';
        if ( $score >= 20 ) return 'Fejlesztendő';
        return 'Gyenge';
    }

    /**
     * Get color for score
     */
    public static function get_score_color( $score ) {
        if ( $score >= 80 ) return '#00a32a';
        if ( $score >= 60 ) return '#72aee6';
        if ( $score >= 40 ) return '#dba617';
        return '#d63638';
    }
}
