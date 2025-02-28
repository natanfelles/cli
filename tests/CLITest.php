<?php
/*
 * This file is part of Aplus Framework CLI Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\CLI;

use Framework\CLI\CLI;
use Framework\CLI\Streams\Stderr;
use Framework\CLI\Streams\Stdout;
use Framework\CLI\Styles\BackgroundColor;
use Framework\CLI\Styles\ForegroundColor;
use Framework\CLI\Styles\Format;
use PHPUnit\Framework\TestCase;

final class CLITest extends TestCase
{
    protected function setUp() : void
    {
        Stdout::init();
    }

    protected function tearDown() : void
    {
        Stdout::reset();
    }

    public function testWrite() : void
    {
        CLI::write('Hello!');
        self::assertSame("Hello!\n", Stdout::getContents());
        Stdout::reset();
        CLI::write('Hello!', ForegroundColor::red);
        self::assertStringContainsString("\033[0;31mHello!", Stdout::getContents());
        Stdout::reset();
        CLI::write('Hello!', null, null, 2);
        self::assertSame("He\nll\no!\n", Stdout::getContents());
    }

    public function testBeep() : void
    {
        CLI::beep(2);
        self::assertSame("\x07\x07", Stdout::getContents());
    }

    public function testNewLine() : void
    {
        CLI::newLine(2);
        self::assertSame(\PHP_EOL . \PHP_EOL, Stdout::getContents());
    }

    public function testLiveLine() : void
    {
        for ($i = 0; $i <= 10; $i++) {
            $percent = $i * 10 . '%';
            //$percent = \str_pad($percent, 5, ' ', \STR_PAD_LEFT);
            $progress = '';
            //$progress = \str_repeat('#', $i);
            //$progress = \str_pad($progress, 10);
            $finalize = $i === 10;
            CLI::liveLine($progress . $percent, $finalize);
            //\sleep(1);
            if ($finalize) {
                $percent .= \PHP_EOL;
            }
            self::assertSame("\33[2K\r{$percent}", Stdout::getContents());
            Stdout::reset();
        }
    }

    public function testIsWindows() : void
    {
        self::assertFalse(CLI::isWindows());
    }

    /**
     * Get terminal width.
     * The default is 80. But windows can become wider or smaller when resized.
     *
     * @return int
     */
    protected function getTerminalWidth() : int
    {
        $expected = 80;
        $width = (int) \shell_exec('tput cols');
        if ($width) {
            $expected = $width;
        }
        return $expected;
    }

    public function testWidth() : void
    {
        self::assertSame($this->getTerminalWidth(), CLI::getWidth());
        $cli = new class() extends CLI {
            public static function isWindows() : bool
            {
                return true;
            }
        };
        self::assertSame(100, $cli::getWidth(100));
    }

    public function testWrap() : void
    {
        $width = $this->getTerminalWidth();
        $line = [];
        $line[0] = \str_repeat('a', $width);
        $line[1] = \str_repeat('a', $width);
        $line[2] = \str_repeat('a', $width);
        self::assertSame(
            $line[0] . \PHP_EOL . $line[1] . \PHP_EOL . $line[2],
            CLI::wrap(\implode($line), $width)
        );
    }

    public function testClear() : void
    {
        CLI::clear();
        self::assertSame("\e[H\e[2J", Stdout::getContents());
    }

    public function testError() : void
    {
        Stderr::init();
        CLI::error('Whoops!', null);
        self::assertStringContainsString('Whoops!', Stderr::getContents());
    }

    public function testTable() : void
    {
        CLI::table([[1, 'John'], [2, 'Mary']]);
        $table = <<<'EOL'
            +---+------+
            | 1 | John |
            | 2 | Mary |
            +---+------+

            EOL;
        self::assertSame($table, Stdout::getContents());
        Stdout::reset();
        CLI::table([[1, 'John'], [2, 'Mary']], ['ID', 'Name']);
        $table = <<<'EOL'
            +----+------+
            | ID | Name |
            +----+------+
            | 1  | John |
            | 2  | Mary |
            +----+------+

            EOL;
        self::assertSame($table, Stdout::getContents());
    }

    public function testStyle() : void
    {
        self::assertSame("foo\033[0m", CLI::style('foo'));
        self::assertSame(
            "\033[0;31mfoo\033[0m",
            CLI::style('foo', ForegroundColor::red)
        );
        self::assertSame(
            "\033[0;31mfoo\033[0m",
            CLI::style('foo', 'red')
        );
        self::assertSame(
            "\033[0;31m\033[44mfoo\033[0m",
            CLI::style('foo', ForegroundColor::red, BackgroundColor::blue)
        );
        self::assertSame(
            "\033[0;31m\033[44mfoo\033[0m",
            CLI::style('foo', 'red', 'blue')
        );
        self::assertSame(
            "\033[0;31m\033[44m\033[1m\033[3mfoo\033[0m",
            CLI::style('foo', ForegroundColor::red, BackgroundColor::blue, [
                Format::bold,
                Format::italic,
            ])
        );
        self::assertSame(
            "\033[0;31m\033[44m\033[1m\033[3mfoo\033[0m",
            CLI::style('foo', 'red', 'blue', [
                'bold',
                'italic',
            ])
        );
    }

    public function testStyleWithInvalidColor() : void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage(
            '"bar" is not a valid backing value for enum Framework\CLI\Styles\ForegroundColor'
        );
        CLI::style('foo', 'bar');
    }

    public function testStyleWithInvalidBackground() : void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage(
            '"baz" is not a valid backing value for enum Framework\CLI\Styles\BackgroundColor'
        );
        CLI::style('foo', null, 'baz');
    }

    public function testStyleWithInvalidFormat() : void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage(
            '"bar" is not a valid backing value for enum Framework\CLI\Styles\Format'
        );
        CLI::style('foo', null, null, [Format::bold, 'bar']);
    }

    public function testBox() : void
    {
        CLI::box('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam'
            . ' sem lacus, rutrum vel neque eu, aliquam aliquet neque.');
        self::assertStringContainsString('Lorem', Stdout::getContents());
    }
}
