<?php

use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public function test_plugin_class_exists()
    {
        $this->assertTrue(class_exists('InEmployeeReports\Plugin'));
    }
}
