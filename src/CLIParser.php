<?php
declare(strict_types=1);

namespace CLIParser;

/**
 * @author Andreas Wahlen
 */
final class CLIParser {

  /**
   * @var string[]
   * @readonly
   * @psalm-var list<string>
   */
  private array $args;
  /**
   * @readonly
   */
  private string $optionsString;
  /**
   * @psalm-var null|list<string>|array<string, array{
   *    filter: int,
   *    flags?: int,
   *    options?: array{
   *      default?: scalar,
   *      ...
   *    },
   *    value_label?: string,
   *    description?: string
   *  }>
   */
  private ?array $allowedOptions = null;
  /**
   * @psalm-var null|array<string, string>
   */
  private ?array $allowedFlags = null;
  private bool $strictMode = false;
  /**
   * @psalm-var array<string, scalar>
   */
  private array $options = [];
  /**
   * @var string[]
   * @psalm-var list<string>
   */
  private array $commands = [];
  /**
   * @var string[]
   * @psalm-var list<string>
   */
  private array $arguments = [];
  /**
   * @var string[]
   * @psalm-var list<string>
   */
  private array $errors = [];
  
  /**
   * @param string[] $args e.g. $_SERVER['argv']
   * @psalm-param list<string> $args
   * @param string $optionsString will be printed inside usage documentation right after the script name and should contain e.g. `--in=<file> [--out=<file>]` 
   */
  public function __construct(array $args, string $optionsString = '') {
    $this->args = $args;
    $this->optionsString = $optionsString;
  }
  
  /**
   * @param array $allowedOptions either a list of names of options (without `--`) or an array mapping option names
   *    to PHP's filter_var structures (array with filter, flags and options). Empty arrays as values will be replaced with
   *    `['filter' => FILTER_DEFAULT]`
   * @psalm-param list<string>|array<string, array{
   *    filter?: int,
   *    flags?: int,
   *    options?: array{
   *      default?: scalar,
   *      ...
   *    },
   *    value_label?: string,
   *    description?: string
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
  
  public function printUsage(): void {
    $usage = "\e[33mUsage:\e[0m".PHP_EOL;
    $script = $_SERVER['argv'][0] ?? 'script.php';
    $usage .= 'php '.$script.' '.$this->optionsString.PHP_EOL.PHP_EOL;
    if(!empty($this->allowedOptions)){
      $usage .= "\e[33mOptions:\e[0m".PHP_EOL;
      $isList = array_is_list($this->allowedOptions);
      $options = [];
      foreach($this->allowedOptions as $key => $option){
        if($isList){
          /**
           * @var string $option
           * @var string|false $flag
           */
          $flag = array_search($option, $this->allowedFlags ?? [], true);
          $options[] = [
            'name' => $option,
            'flag' => $flag,
            'value' => '',
            'description' => '',
            'default' => null
          ];
        } else {
          /**
           * @var string $key
           * @var string|false $flag
           */
          $flag = array_search($key, $this->allowedFlags ?? [], true);
          $options[] = [
            'name' => $key,
            'flag' => $flag,
            'value' => $option['value_label'] ?? '',
            'description' => $option['description'] ?? '',
            'default' => $option['options']['default'] ?? null
          ];
        }
      }
      $maxLength = 0;
      foreach($options as $key => $opt){
        $len = strlen(($opt['flag'] === false ? '' : '-'.$opt['flag'].', ').'--'.$opt['name'].(empty($opt['value']) ? '' : '=<'.$opt['value'].'>'));
        $options[$key]['length'] = $len;
        if($maxLength < $len){
          $maxLength = $len;
        }
      }
      foreach($options as $option){
        $padding = str_pad(' ', $maxLength + 2 - $option['length']);
        $usage .= "  \e[32m".($option['flag'] === false ? '' : '-'.$option['flag'].', ').'--'.$option['name']."\e[0m".(empty($option['value']) ? '' : "=<\e[34m".$option['value']."\e[0m>").
            $padding.$option['description'].($option['default'] === null ? '' : " DEFAULT: \e[34m".strval($option['default'])."\e[0m").PHP_EOL;
      }
      $usage .= PHP_EOL;
    }
    echo $usage;
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
      /**
       * @var scalar $res
       */
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
   * commands do not start with hyphens and are positioned before `--` (end of options)
   * arguments follow behind a `--` (end of options)
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
    $args = $this->args;
    array_shift($args);
    while(($arg = array_shift($args)) !== null){
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
          } else if(($args[0][0] ?? '-') !== '-'){  // is the option not followed by another option/flag but by arguments
            while(($args[0][0] ?? '-') !== '-'){
              /**
               * @psalm-suppress PossiblyNullOperand
               */
              $value .= array_shift($args).' ';
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
          } else if(($args[0][0] ?? '-') !== '-'){  // is the flag not followed by another option/flag but by arguments
            $value = '';
            while(($args[0][0] ?? '-') !== '-'){
              /**
               * @psalm-suppress PossiblyNullOperand
               */
              $value .= array_shift($args).' ';
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
   * @psalm-return array<string, scalar>
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
