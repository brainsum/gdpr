<?php

/**
 * Defines a utility class for creating random data.
 */
class GdprDumpUtilRandom {

  /**
   * Generates a random string of ASCII characters of codes 32 to 126.
   *
   * The generated string includes alpha-numeric characters and common
   * miscellaneous characters. Use this method when testing general input
   * where the content is not restricted.
   *
   * @param int $length
   *   Length of random string to generate.
   *
   * @return string
   *   Randomly generated string.
   *
   * @see GdprDumpUtilRandom::name()
   */
  public function string($length = 8) {

    $str = '';
    for ($i = 0; $i < $length; $i++) {
      $str .= chr(mt_rand(32, 126));
    }

    return $str;
  }

  /**
   * Generates a random string containing letters and numbers.
   *
   * The string will always start with a letter. The letters may be upper or
   * lower case. This method is better for restricted inputs that do not
   * accept certain characters. For example, when testing input fields that
   * require machine readable values (i.e. without spaces and non-standard
   * characters) this method is best.
   *
   * @param int $length
   *   Length of random string to generate.
   *
   * @return string
   *   Randomly generated string.
   *
   * @see GdprDumpUtilRandom::string()
   */
  public function name($length = 8) {
    $values = array_merge(range(65, 90), range(97, 122), range(48, 57));
    $max = count($values) - 1;

    $str = chr(mt_rand(97, 122));
    for ($i = 1; $i < $length; $i++) {
      $str .= chr($values[mt_rand(0, $max)]);
    }

    return $str;
  }

  /**
   * Generate a string that looks like a word.
   *
   * Letters only, alternating consonants and vowels.
   *
   * @param int $length
   *   The desired word length.
   *
   * @return string
   *   The generate random word.
   */
  public function word($length) {
    mt_srand((double) microtime() * 1000000);

    $vowels = [
      "a",
      "e",
      "i",
      "o",
      "u",
    ];
    $cons = [
      "b",
      "c",
      "d",
      "g",
      "h",
      "j",
      "k",
      "l",
      "m",
      "n",
      "p",
      "r",
      "s",
      "t",
      "u",
      "v",
      "w",
      "tr",
      "cr",
      "br",
      "fr",
      "th",
      "dr",
      "ch",
      "ph",
      "wr",
      "st",
      "sp",
      "sw",
      "pr",
      "sl",
      "cl",
      "sh",
    ];

    $num_vowels = count($vowels);
    $num_cons = count($cons);
    $word = '';

    while (strlen($word) < $length) {
      $word .= $cons[mt_rand(0, $num_cons - 1)] . $vowels[mt_rand(0, $num_vowels - 1)];
    }

    return substr($word, 0, $length);
  }

  /**
   * Generates sentences Latin words, often used as placeholder text.
   *
   * @param int $min_word_count
   *   The minimum number of words in the return string. Total word count
   *   can slightly exceed provided this value in order to deliver
   *   sentences of random length.
   * @param bool $capitalize
   *   Uppercase all the words in the string.
   *
   * @return string
   *   Nonsense latin words which form sentence(s).
   */
  public function sentences($min_word_count, $capitalize = FALSE) {
    $dictionary = ["abbas", "abdo", "abico", "abigo", "abluo", "accumsan",
      "acsi", "ad", "adipiscing", "aliquam", "aliquip", "amet", "antehabeo",
      "appellatio", "aptent", "at", "augue", "autem", "bene", "blandit",
      "brevitas", "caecus", "camur", "capto", "causa", "cogo", "comis",
      "commodo", "commoveo", "consectetuer", "consequat", "conventio", "cui",
      "damnum", "decet", "defui", "diam", "dignissim", "distineo", "dolor",
      "dolore", "dolus", "duis", "ea", "eligo", "elit", "enim", "erat",
      "eros", "esca", "esse", "et", "eu", "euismod", "eum", "ex", "exerci",
      "exputo", "facilisi", "facilisis", "fere", "feugiat", "gemino",
      "genitus", "gilvus", "gravis", "haero", "hendrerit", "hos", "huic",
      "humo", "iaceo", "ibidem", "ideo", "ille", "illum", "immitto",
      "importunus", "imputo", "in", "incassum", "inhibeo", "interdico",
      "iriure", "iusto", "iustum", "jugis", "jumentum", "jus", "laoreet",
      "lenis", "letalis", "lobortis", "loquor", "lucidus", "luctus", "ludus",
      "luptatum", "macto", "magna", "mauris", "melior", "metuo", "meus",
      "minim", "modo", "molior", "mos", "natu", "neo", "neque", "nibh",
      "nimis", "nisl", "nobis", "nostrud", "nulla", "nunc", "nutus", "obruo",
      "occuro", "odio", "olim", "oppeto", "os", "pagus", "pala", "paratus",
      "patria", "paulatim", "pecus", "persto", "pertineo", "plaga", "pneum",
      "populus", "praemitto", "praesent", "premo", "probo", "proprius",
      "quadrum", "quae", "qui", "quia", "quibus", "quidem", "quidne", "quis",
      "ratis", "refero", "refoveo", "roto", "rusticus", "saepius",
      "sagaciter", "saluto", "scisco", "secundum", "sed", "si", "similis",
      "singularis", "sino", "sit", "sudo", "suscipere", "suscipit", "tamen",
      "tation", "te", "tego", "tincidunt", "torqueo", "tum", "turpis",
      "typicus", "ulciscor", "ullamcorper", "usitas", "ut", "utinam",
      "utrum", "uxor", "valde", "valetudo", "validus", "vel", "velit",
      "veniam", "venio", "vereor", "vero", "verto", "vicis", "vindico",
      "virtus", "voco", "volutpat", "vulpes", "vulputate", "wisi", "ymo",
      "zelus",
    ];
    $dictionary_flipped = array_flip($dictionary);
    $greeking = '';

    if (!$capitalize) {
      $words_remaining = $min_word_count;
      while ($words_remaining > 0) {
        $sentence_length = mt_rand(3, 10);
        $words = array_rand($dictionary_flipped, $sentence_length);
        $sentence = implode(' ', $words);
        $greeking .= ucfirst($sentence) . '. ';
        $words_remaining -= $sentence_length;
      }
    }
    else {
      // Use slightly different method for titles.
      $words = array_rand($dictionary_flipped, $min_word_count);
      $words = is_array($words) ? implode(' ', $words) : $words;
      $greeking = ucwords($words);
    }
    return trim($greeking);
  }

  /**
   * Generate paragraphs separated by double new line.
   *
   * @param int $paragraph_count
   *   The number of paragraphs to generate.
   *
   * @return string
   *   The generated paragraphs.
   */
  public function paragraphs($paragraph_count = 12) {
    $output = '';
    for ($i = 1; $i <= $paragraph_count; $i++) {
      $output .= $this->sentences(mt_rand(20, 60)) . "\n\n";
    }
    return $output;
  }

}
