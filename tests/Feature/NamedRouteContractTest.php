<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class NamedRouteContractTest extends TestCase
{
    public function test_all_literal_route_references_have_a_named_route(): void
    {
        $defined = collect(Route::getRoutes()->getRoutesByName())->keys();
        $roots = [app_path(), resource_path('views')];
        $references = collect();
        $pattern = '/(?:route|to_route)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/';

        foreach ($roots as $root) {
            $files = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)),
                '/\.php$/'
            );
            foreach ($files as $file) {
                preg_match_all($pattern, file_get_contents($file->getPathname()), $matches);
                $references->push(...$matches[1]);
            }
        }

        $missing = $references->unique()->diff($defined)->sort()->values();

        $this->assertSame([], $missing->all(), 'Missing named routes: '.$missing->implode(', '));
    }

    public function test_removed_legacy_workflows_are_not_exposed_as_placeholder_routes(): void
    {
        foreach (['closing.index', 'stock-counts.index', 'cheques.index', 'installments.index', 'payroll.index', 'admin.finance'] as $name) {
            $this->assertFalse(Route::has($name), "Legacy route {$name} must remain unavailable until its real workflow is restored.");
        }
    }
}
