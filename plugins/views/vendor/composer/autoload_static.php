<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitf7887527659a2ee20565693aa7585160
{
    public static $prefixLengthsPsr4 = array (
        'G' => 
        array (
            'Grav\\Plugin\\Views\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Grav\\Plugin\\Views\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitf7887527659a2ee20565693aa7585160::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitf7887527659a2ee20565693aa7585160::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
