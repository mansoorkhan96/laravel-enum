<?php

namespace Spatie\Enum\Laravel\Http\Middleware;

use BadMethodCallException;
use Closure;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;
use Spatie\Enum\Enum;
use Spatie\Enum\Laravel\Exceptions\InvalidEnumValueException;

class SubstituteBindings
{
    /**
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $route = $request->route();

        $parameters = $route->parameters();

        foreach ($route->signatureParameters(Enum::class) as $parameter) {
            $parameterName = static::getParameterName($parameter->getName(), $parameters);
            if ($parameterName === null) {
                continue;
            }

            $parameterValue = $parameters[$parameterName];
            if ($parameterValue instanceof Enum) {
                continue;
            }

            try {
                $instance = forward_static_call(
                    /** @see \Spatie\Enum\Laravel\Enum::make() */
                    [Reflector::getParameterClassName($parameter), 'make'],
                    $parameterValue
                );
            } catch (BadMethodCallException $e) {
                throw new InvalidEnumValueException($e->getMessage(), $e);
            }

            $route->setParameter($parameterName, $instance);
        }

        return $next($request);
    }

    protected function getParameterName(string $name, array $parameters): ?string
    {
        if (array_key_exists($name, $parameters)) {
            return $name;
        }

        $snakedName = Str::snake($name);
        if (array_key_exists($snakedName, $parameters)) {
            return $snakedName;
        }

        return null;
    }
}
