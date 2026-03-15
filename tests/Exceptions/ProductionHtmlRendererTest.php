<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use EzPhp\Exceptions\ProductionHtmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ProductionHtmlRendererTest
 *
 * @package Tests\Exceptions
 */
#[CoversClass(ProductionHtmlRenderer::class)]
final class ProductionHtmlRendererTest extends TestCase
{
    /**
     * @return void
     */
    public function test_render_404_contains_status_code(): void
    {
        $html = (new ProductionHtmlRenderer())->render(404);

        $this->assertStringContainsString('404', $html);
    }

    /**
     * @return void
     */
    public function test_render_500_contains_status_code(): void
    {
        $html = (new ProductionHtmlRenderer())->render(500);

        $this->assertStringContainsString('500', $html);
    }

    /**
     * @return void
     */
    public function test_render_404_contains_not_found_title(): void
    {
        $html = (new ProductionHtmlRenderer())->render(404);

        $this->assertStringContainsString('Not Found', $html);
    }

    /**
     * @return void
     */
    public function test_render_500_contains_server_error_title(): void
    {
        $html = (new ProductionHtmlRenderer())->render(500);

        $this->assertStringContainsString('Internal Server Error', $html);
    }

    /**
     * @return void
     */
    public function test_render_returns_valid_html_document(): void
    {
        $html = (new ProductionHtmlRenderer())->render(404);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    /**
     * @return void
     */
    public function test_render_uses_custom_template_when_file_exists(): void
    {
        $dir = sys_get_temp_dir() . '/ez-php-prod-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/404.php', '<p>Custom not found</p>');

        $html = (new ProductionHtmlRenderer($dir))->render(404);

        $this->assertStringContainsString('Custom not found', $html);

        unlink($dir . '/404.php');
        rmdir($dir);
    }

    /**
     * @return void
     */
    public function test_render_falls_back_to_built_in_when_template_missing(): void
    {
        $html = (new ProductionHtmlRenderer('/non/existent/path'))->render(404);

        $this->assertStringContainsString('404', $html);
        $this->assertStringContainsString('Not Found', $html);
    }

    /**
     * @return void
     */
    public function test_render_unknown_status_falls_back_to_generic_error(): void
    {
        $html = (new ProductionHtmlRenderer())->render(503);

        $this->assertStringContainsString('503', $html);
        $this->assertStringContainsString('Error', $html);
    }

    /**
     * @return void
     */
    public function test_render_template_receives_status_variable(): void
    {
        $dir = sys_get_temp_dir() . '/ez-php-prod-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/500.php', '<p>Error code: <?= $status ?></p>');

        $html = (new ProductionHtmlRenderer($dir))->render(500);

        $this->assertStringContainsString('Error code: 500', $html);

        unlink($dir . '/500.php');
        rmdir($dir);
    }
}
