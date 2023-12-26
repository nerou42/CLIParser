<?php
/**
 * @author Andreas Wahlen
 */

declare(strict_types=1);

// polyfill for PHP <8.1
if(!function_exists('array_is_list')){
  function array_is_list(array $list): bool {
    return array_values($list) === $list;
  }
}
