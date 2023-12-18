<?php
declare(strict_types=1);

namespace CLIParser\test;

use PHPUnit\Framework\TestCase;
use CLIParser\CLIParser;

/**
 * @author Andreas Wahlen
 */
class CLIParserTest extends TestCase {

  public static function provideData(): array {
    $largeArgs = ['', 'cmd1', 'cmd2', '--opt1=val1', 'cmd3', '--opt2', 'val2', '-abc', '-def=val3',
      '-ghi', 'val4', 'val5', '--opt3', '--', 'arg1', 'arg2'];
    return [
      [
        $largeArgs,
        false,
        true,
        ['opt1' => 'val1', 'opt2' => 'val2', 'a' => true, 'b' => true, 'c' => true, 'd' => true, 'e' => true,
          'f' => 'val3', 'g' => true, 'h' => true, 'i' => 'val4 val5', 'opt3' => true],
        ['cmd1', 'cmd2', 'cmd3'],
        ['arg1', 'arg2']
      ],
      [
        ['', 'cmd1', 'cmd2', '--opt4=val1', 'cmd3', '--opt5', 'val2', '-ab', 'val3', '--opt3', '--', 'arg1', 'arg2'],
        false,
        true,
        ['opt4' => 'val1', 'opt5' => 'val2', 'opt1' => true, 'opt2' => 'val3', 'opt3' => true],
        ['cmd1', 'cmd2', 'cmd3'],
        ['arg1', 'arg2'],
        ['opt1', 'opt2', 'opt3', 'opt4', 'opt5'],
        ['a' => 'opt1', 'b' => 'opt2']
      ],
      [
        ['', 'cmd1', 'cmd2', '--opt4=val1', 'cmd3', '--opt5', 'val2', '-ab', 'val3', '--opt3', '--', 'arg1', 'arg2'],
        false,
        true,
        ['opt4' => 'val1', 'opt2' => 'val3', 'opt3' => true],
        ['cmd1', 'cmd2', 'cmd3'],
        ['arg1', 'arg2'],
        ['opt2', 'opt3', 'opt4'],
        ['a' => 'opt1', 'b' => 'opt2']
      ],
      [
        ['', 'cmd1', 'cmd2', '--opt4=val1', 'cmd3', '--opt5', 'val2', '-ab', 'val3', '--opt3', '--', 'arg1', 'arg2'],
        true,
        false,
        [],
        [],
        [],
        ['opt2', 'opt3', 'opt4'],
        ['a' => 'opt1', 'b' => 'opt2']
      ],
      [
        ['', '--opt1=123'],
        false,
        true,
        ['opt1' => 123],
        [],
        [],
        ['opt1' => ['filter' => FILTER_VALIDATE_INT]]
      ],
      [
        ['', '--opt1=abc'],
        false,
        true,
        [],
        [],
        [],
        ['opt1' => ['filter' => FILTER_VALIDATE_INT]]
      ],
    ];
  }
  
  /**
   * @dataProvider provideData
   */
  public function test(array $args, bool $strict, bool $expectedResult, array $expectedOptions, array $expectedCommands,
      array $expectedArguments, array $allowedOptions = null, array $allowedFlags = null): void {
    $parser = new CLIParser($args);
    $parser->setStrictMode($strict);
    if($allowedOptions !== null){
      $parser->setAllowedOptions($allowedOptions);
    }
    if($allowedFlags !== null){
      $parser->setAllowedFlags($allowedFlags);
    }
    $this->assertSame($expectedResult, $parser->parse());
    $this->assertSame($expectedOptions, $parser->getOptions());
    $this->assertSame($expectedCommands, $parser->getCommands());
    $this->assertSame($expectedArguments, $parser->getArguments());
  }
}
