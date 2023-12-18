<?php
declare(strict_types=1);

namespace CLIParser;

/**
 * @author Andreas Wahlen
 */
class CLIParser {

  /**
   * @var list<string>
   */
  private array $args;
  /**
   * @var null|list<string>|array<string, array{
   *    filter: int,
   *    flags?: int,
   *    options?: array
   *  }>
   */
  private ?array $allowedOptions = null;
  /**
   * @var null|array<string, string>
   */
  private ?array $allowedFlags = null;
  private bool $strictMode = false;
  /**
   * @var array<string, true|string>
   */
  private array $options = [];
  /**
   * @var list<string>
   */
  private array $commands = [];
  /**
   * @var list<string>
   */
  private array $arguments = [];
  /**
   * @var list<string>
   */
  private array $errors = [];
  
  /**
   * @param string[] $args e.g. $_SERVER['argv']
   * @psalm-param list<string> $args
   */
  public function __construct(array $args) {
    array_shift($args);
    $this->args = $args;
  }
  
  /**
   * @param array $allowedOptions either a list of names of options (without --) or an array mapping option names
   *    to PHP's filter_var structures (array with filter, flags and options). Empty arrays as values will be replaced with
   *    ['filter' => FILTER_DEFAULT]
   * @psalm-param list<string>|array<string, array{
   *    filter?: int,
   *    flags?: int,
   *    options?: array
   *  }> $allowedOptions
   * @see https://www.php.net/manual/en/function.filter-var-array.php
   */
  public function setAllowedOptions(array $allowedOptions): void {
    if(!array_is_list($allowedOptions)){
      /**
       * @var array{
       *    filter?: int,
       *    flags?: int,
       *    options?: array
       *  } $option
       */
      foreach($allowedOptions as $name => $option){
        if(empty($option)){
          $this->allowedOptions[$name] = ['filter' => FILTER_DEFAULT];
        } else {
          $this->allowedOptions[$name] = $option;
        }
      }
    } else {
      $this->allowedOptions = $allowedOptions;
    }
  }
  
  /**
   * @param array $allowedFlags an array mapping flag names to option names that have the same logic and validation
   * @psalm-param array<string, string> $allowedFlags
   */
  public function setAllowedFlags(array $allowedFlags): void {
    $this->allowedFlags = $allowedFlags;
  }
  
  /**
   * if set to true, parse() will abort when options/flags are given wich are not allowed,
   * otherwise parse() will just ignore additional options/flags
   */
  public function setStrictMode(bool $strictMode): void {
    $this->strictMode = $strictMode;
  }
  
  private function validateOption(string $option, ?string $value): bool {
    if($this->allowedOptions === null || (array_is_list($this->allowedOptions) && in_array($option, $this->allowedOptions))){
      $this->options[$option] = $value ?? true;
      return true;
    } else if(array_key_exists($option, $this->allowedOptions)){
      /**
       * @var array{
       *    filter: int,
       *    flags?: int,
       *    options?: array
       *  } $filterConf
       */
      $filterConf = $this->allowedOptions[$option];
      /** @var false|string $res */
      $res = filter_var($value, $filterConf['filter'], $filterConf);
      if($res === false){
        $this->errors[] = 'Invalid value for option "'.$option.'": "'.(isset($value) ? '"'.$value.'"' : 'null').'"';
        return false;
      } else {
        $this->options[$option] = $res;
        return true;
      }
    } else {
      $this->errors[] = 'Unknown option "'.$option.'"';
      return false;
    }
  }
  
  private function validateFlag(string $flag, ?string $value): bool {
    if($this->allowedFlags === null){
      $this->options[$flag] = $value ?? true;
      return true;
    } else if(array_key_exists($flag, $this->allowedFlags) && $this->allowedOptions !== null){
      return $this->validateOption($this->allowedFlags[$flag], $value);
    } else {
      $this->errors[] = 'Unknown flag "'.$flag.'"';
      return false;
    }
  }
  
  /**
   * flags start with single hyphens
   * options start with double hyphens
   * commands do not start with hyphens and are positioned before "--" (end of options)
   * arguments follow behind a "--" (end of options)
   * @author thomas.harding@laposte.net
   * @author Andreas Wahlen
   * @see https://www.php.net/manual/en/features.commandline.php#83843
   */
  public function parse(): bool {
    $this->errors = [];
    if($this->allowedOptions !== null){
      foreach($this->allowedOptions as $key => $option){
        if(is_string($key) && isset($option['options']['default'])){
          $this->options[$key] = strval($option['options']['default']);
        }
      }
    }
    
    $endofoptions = false;
    while(($arg = array_shift($this->args)) !== null){
      if($endofoptions){    // if we have reached end of options, we cast all remaining argvs as arguments
        $this->arguments[] = $arg;
        
      } else if(mb_substr($arg, 0, 2) === '--'){   // is it an option? (prefixed with --)
        if(!isset($arg[3])){   // is it the end of options flag?
          $endofoptions = true;
        } else {
          $value = '';
          $option = mb_substr($arg, 2);
          
          $equalPos = mb_strpos($option, '=');
          if($equalPos !== false){    // is it the syntax '--option=value'?
            $value = mb_substr($option, $equalPos + 1);
            $option = mb_substr($option, 0, $equalPos);
          } else if(($this->args[0][0] ?? '-') !== '-'){  // is the option not followed by another option/flag but by arguments
            while(($this->args[0][0] ?? '-') !== '-'){
              $value .= array_shift($this->args).' ';
            }
            $value = rtrim($value, ' ');
          }
          $valRes = $this->validateOption($option, mb_strlen($value) > 0 ? $value : null);
          if($valRes === false && $this->strictMode){
            $this->reset();
            return false;
          }
        }
        
      } else if(mb_substr($arg, 0, 1) === '-'){    // is it a flag or a serial of flags? (prefixed with -)
        $flag = null;
        $argLen = mb_strlen($arg);
        for($i = 1; $i < $argLen; $i++){
          $chr = mb_substr($arg, $i, 1);
          if($chr !== '='){
            $flag = $chr;
            $valRes = $this->validateFlag($flag, null);
            if($valRes === false && $this->strictMode){
              $this->reset();
              return false;
            }
          } else {
            break;
          }
        }
        if(isset($flag, $chr)){
          $value = null;
          if($chr === '='){    // is it the syntax '-f=value'?
            $value = mb_substr($arg, $i + 1);
          } else if(($this->args[0][0] ?? '-') !== '-'){  // is the flag not followed by another option/flag but by arguments
            $value = '';
            while(($this->args[0][0] ?? '-') !== '-'){
              $value .= array_shift($this->args).' ';
            }
            $value = rtrim($value, ' ');
          }
          $valRes = $this->validateFlag($flag, $value);
          if($valRes === false && $this->strictMode){
            $this->reset();
            return false;
          }
        }
        
      } else {    // finally, it is not option, nor flag, nor argument
        $this->commands[] = $arg;
      }
    }
    
    /*if(empty($this->options)){
      $this->arguments = array_merge($this->commands, $this->arguments);
      $this->commands = [];
    }*/
    return true;
  }
  
  private function reset(): void {
    $this->options = [];
    $this->commands = [];
    $this->arguments = [];
  }
  
  /**
   * @psalm-return array<string, true|string>
   * @psalm-mutation-free
   */
  public function getOptions(): array {
    return $this->options;
  }
  
  /**
   * @return string[]
   * @psalm-return list<string>
   * @psalm-mutation-free
   */
  public function getCommands(): array {
    return $this->commands;
  }
  
  /**
   * @return string[]
   * @psalm-return list<string>
   * @psalm-mutation-free
   */
  public function getArguments(): array {
    return $this->arguments;
  }
  
  /**
   * @return string[]
   * @psalm-return list<string>
   * @psalm-mutation-free
   */
  public function getErrors(): array {
    return $this->errors;
  }
}
