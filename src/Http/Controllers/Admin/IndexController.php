<?php

namespace Amethyst\Http\Controllers\Admin;

use Amethyst\Api\Http\Controllers\RestController;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Railken\Lem\Attributes;

class IndexController extends RestController
{
    public function __construct()
    {
        $this->middleware(\Spatie\ResponseCache\Middlewares\CacheResponse::class);
    }

    public function index(Request $request)
    {
        return $this->retrieveInfoCached();
    }

    public function retrieveInfo()
    {
        $events = [];
        $dataBuilders = [];

        $amethyst = ['data' => $this->retrieveData()];

        $lang = [];

        $helper = new \Amethyst\Common\Helper();

        foreach (glob(resource_path('/lang/vendor/*')) as $pathPackage) {
            $packageName = basename($pathPackage);
            foreach (glob($pathPackage.'/*') as $pathLocale) {
                if (is_dir($pathLocale)) {
                    $locale = basename($pathLocale);

                    foreach (glob($pathLocale.'/*') as $file) {
                        $data = basename($file, '.php');
                        $trans = trans(sprintf('%s::%s', $packageName, $data));

                        $lang[$data] = is_array($trans) ? $trans : [];
                    }
                }
            }
        }

        foreach ($helper->getPackages() as $packageName) {
            foreach ($helper->getDataByPackageName($packageName) as $data) {
                $trans = trans(sprintf('amethyst-%s::%s', $packageName, $data));
                $lang[$data] = is_array($trans) ? $trans : [];
            }
        }

        return array_merge($amethyst, [
            'lang'      => $lang,
            'discovery' => [
                'events'        => $events,
                'data_builders' => $dataBuilders,
            ],
        ]);
    }

    public function retrieveData()
    {
        $helper = new \Amethyst\Common\Helper();

        return $helper->getData()->map(function ($data) use ($helper) {
            $name = $helper->getNameDataByModel(Arr::get($data, 'model'));
            $manager = app(Arr::get($data, 'manager'));

            return [
                'name'       => $name,
                'attributes' => $manager->getAttributes()->map(function ($attribute) use ($manager) {
                    return $this->retrieveAttribute($manager, $attribute);
                })->toArray(),
                'relations' => collect(app('eloquent.mapper')->getFinder()->relations(Arr::get($data, 'model')))->map(function ($relation, $key) use ($helper) {
                    $relation = (object) $relation;

                    $return = [
                        'key'   => $relation->key,
                        'type'  => $relation->type,
                        'data'  => $helper->getNameDataByModel($relation->model),
                        'scope' => app('amethyst')->parseScope($relation->model, $relation->scope),
                    ];

                    if (isset($relation->intermediate)) {
                        $return = array_merge($return, [
                            'intermediate'    => $helper->getNameDataByModel($relation->intermediate),
                            'foreignPivotKey' => $relation->foreignPivotKey,
                            'relatedPivotKey' => $relation->relatedPivotKey,
                        ]);
                    }

                    return $return;
                })->values(),
                'descriptor' => app(Arr::get($data, 'manager'))->getDescriptor(),
            ];
        })->values()->toArray();
    }

    public function retrieveAttribute($manager, $attribute)
    {
        $params = [
            'name'       => $attribute->getName(),
            'type'       => $attribute->getType(),
            'fillable'   => (bool) $attribute->getFillable(),
            'required'   => (bool) $attribute->getRequired(),
            'unique'     => (bool) $attribute->getUnique(),
            'hidden'     => (bool) $attribute->getHidden(),
            'default'    => $attribute->getDefault($manager->newEntity()),
            'descriptor' => $attribute->getDescriptor(),
        ];

        if ($attribute instanceof Attributes\EnumAttribute) {
            $params = array_merge($params, [
                'options' => $attribute->getOptions(),
            ]);
        }

        if ($attribute instanceof Attributes\BelongsToAttribute || $attribute instanceof Attributes\MorphToAttribute) {
            $params = array_merge($params, [
                'relation' => $attribute->getRelationName(),
            ]);
        }

        return $params;
    }
}
