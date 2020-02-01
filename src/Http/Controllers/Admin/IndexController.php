<?php

namespace Amethyst\Http\Controllers\Admin;

use Amethyst\Core\Http\Controllers\RestController;
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
        $amethyst = [];
        $helper = new \Amethyst\Core\Helper();

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
            'lang'      => $lang
        ]);
    }
}
