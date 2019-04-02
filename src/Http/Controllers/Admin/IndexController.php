<?php

namespace Railken\Amethyst\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Railken\Amethyst\Api\Http\Controllers\RestController;
use Railken\Amethyst\Contracts\DataBuilderContract;
use Railken\EloquentMapper\Mapper;

class IndexController extends RestController
{
    public function index(Request $request)
    {
        $user = $request->user('api');

        $endpoints = Collection::make(Route::getRoutes())
        ->filter(function ($route) use ($user) {
            $middleware = isset($route->action['middleware']) ? $route->action['middleware'] : null;

            if (!is_array($middleware)) {
                $middleware = [$middleware];
            }

            if (!in_array('api', $middleware, true) && !in_array('auth:api', $middleware, true)) {
                return false;
            }

            if (in_array('auth:api', $middleware, true) && $user == null) {
                return false;
            }

            if (in_array('admin', $middleware, true) && $user->role !== 'admin') {
                return false;
            }

            return true;
        })
        ->sortBy(function ($route) {
            return $route->uri;
        })
        ->map(function ($route) {
            return [
                'methods' => $route->methods,
                'uri'     => $route->uri !== '/' ? '/'.$route->uri : '/',
            ];
        })->values()->toArray();

        $events = [];
        $dataBuilders = [];

        $amethyst = ['data' => $this->retrieveData()];

        foreach (Config::get('amethyst.event-logger.models-loggable') as $model) {
            $events = array_merge($events, [
                    'eloquent.created: '.$model,
                    'eloquent.updated: '.$model,
                    'eloquent.removed: '.$model,
                ]);
        }

        foreach (Config::get('amethyst.event-logger.events-loggable') as $class) {
            $events = array_merge(
                    $events,
                    $this->findCachedClasses('app', $class)
                );
        }

        $dataBuilders = array_merge(
                $this->findCachedClasses(base_path('app'), DataBuilderContract::class),
                $this->findCachedClasses(base_path('vendor/railken/amethyst-*/src'), DataBuilderContract::class)
            );

        return array_merge($amethyst, [
            'discovery' => [
                'events'        => $events,
                'data_builders' => $dataBuilders,
            ]
        ]);
    }

    public function findCachedClasses($directory, $subclass)
    {
        if (!file_exists($directory)) {
            return [];
        }

        $key = 'api.info.classes:'.$directory.$subclass;

        $value = Cache::get($key, null);

        if ($value === null) {
            $value = $this->findClasses($directory, $subclass);
        }

        Cache::put($key, $value, 60);

        return $value;
    }

    public function findClasses($directory, $subclass)
    {
        $finder = new \Symfony\Component\Finder\Finder();
        $iter = new \hanneskod\classtools\Iterator\ClassIterator($finder->in($directory));

        return array_keys($iter->type($subclass)->where('isInstantiable')->getClassMap());
    }

    public function retrieveData()
    {
        $helper = new \Railken\Amethyst\Common\Helper();

        return $helper->getData()->map(function ($data) use ($helper) {
            $name = $helper->getNameDataByModel(Arr::get($data, 'model'));

            return [
                'name'       => $name,
                'attributes' => app(Arr::get($data, 'manager'))->getAttributes()->map(function ($attribute) {
                    return [
                        'name'     => $attribute->getName(),
                        'type'     => preg_replace('/Attribute$/', '', (new \ReflectionClass($attribute))->getShortName()),
                        'fillable' => (bool) $attribute->getFillable(),
                        'required' => (bool) $attribute->getRequired(),
                        'unique'   => (bool) $attribute->getUnique(),
                    ];
                })->toArray(),
                'relations' => collect(Mapper::relations(Arr::get($data, 'model')))->map(function ($relation, $key) use ($helper) {
                    return [
                        'key'  => $key,
                        'type' => $relation->type,
                        'data' => $helper->getNameDataByModel($relation->model),
                    ];
                })->values(),
            ];
        });
    }
}