<?php

namespace App\Graphing\Legacy;

use App\Graphing\Exceptions\GraphRenderFailed;
use Generator;
use LibreNMS\Exceptions\RrdGraphException;
use LogicException;

/**
 * Runs a legacy auth file and then, optionally, a graph template in one shared variable scope.
 * Templates depend on everything the auth file defined, including references such as `global`,
 * so both files must execute in the same scope. A generator keeps the scope alive between them.
 */
final class LegacyTemplateRunner
{
    private readonly Generator $scope;
    private bool $templateRan = false;

    /**
     * @param  array<string, mixed>  $variables  initial variables for the auth file
     */
    public function __construct(string $authFile, array $variables)
    {
        $this->scope = (static function (string $__auth, array $__variables): Generator {
            extract($__variables, EXTR_SKIP);
            unset($__variables);

            require $__auth;

            $__template = yield get_defined_vars();

            if (is_string($__template)) {
                require $__template;

                yield get_defined_vars();
            }
        })($authFile, $variables);
    }

    /**
     * Run the auth file
     *
     * @return array<string, mixed> variables defined after the auth file ran
     */
    public function auth(): array
    {
        return $this->clean($this->inLegacyEnvironment(fn () => $this->scope->current()));
    }

    /**
     * Run the graph template in the auth file's scope. May only be called once.
     *
     * @return array<string, mixed> variables defined after the template ran
     */
    public function template(string $file): array
    {
        if ($this->templateRan) {
            throw new LogicException('Legacy graph template already ran');
        }

        $this->auth(); // make sure auth has run
        $this->templateRan = true;

        return $this->clean($this->inLegacyEnvironment(fn () => $this->scope->send($file)));
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function inLegacyEnvironment(callable $callback): mixed
    {
        if (! defined('IGNORE_ERRORS')) {
            define('IGNORE_ERRORS', true);
        }

        // legacy templates use relative includes
        $previousCwd = getcwd();
        chdir(base_path());

        try {
            include_once base_path('includes/dbFacile.php');
            include_once base_path('includes/common.php');
            include_once base_path('includes/html/functions.inc.php');
            include_once base_path('includes/rewrites.php');

            return $callback();
        } catch (RrdGraphException $e) {
            throw GraphRenderFailed::fromLegacy($e);
        } finally {
            if ($previousCwd !== false) {
                chdir($previousCwd);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function clean(mixed $variables): array
    {
        if (! is_array($variables)) {
            return [];
        }

        return array_filter($variables, fn ($key) => ! str_starts_with((string) $key, '__'), ARRAY_FILTER_USE_KEY);
    }
}
