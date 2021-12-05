<?php

declare(strict_types=1);
/**
 * This file is part of hyperf server projects.
 */
namespace Test\Cases\Utils;

use Hyperf\Extend\Utils\I18N;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class I18NTest extends TestCase
{
    public function testText()
    {
        $this->assertEquals('服务器出现错误，请稍后再试', I18N::t('common', 'error.server', 'cn'));
        $this->assertEquals('服務器出現錯誤，請稍後再試', I18N::t('common', 'error.server', 'hant'));
    }
}
