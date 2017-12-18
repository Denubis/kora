<?php

  /**
   * Hyphenates a string
   *
   * @return string - hyphenated
   */
  function str_hyphenated($string) {
    return strtolower(preg_replace("/[^\w]+/", "-", $string));
  }

/**
 * Gets the available set of languages in the installation
 *
 * @return array - the languages
 */
 function getLangs() {
     return \Illuminate\Support\Facades\Config::get('app.locales_supported');
 }
