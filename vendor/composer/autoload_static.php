<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit522b94383911930808795263f051f99a
{
    public static $files = array (
        '538ca81a9a966a6716601ecf48f4eaef' => __DIR__ . '/..' . '/opis/closure/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'Te\\' => 3,
        ),
        'O' => 
        array (
            'Opis\\Closure\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Te\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'Opis\\Closure\\' => 
        array (
            0 => __DIR__ . '/..' . '/opis/closure/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Opis\\Closure\\Analyzer' => __DIR__ . '/..' . '/opis/closure/src/Analyzer.php',
        'Opis\\Closure\\ClosureContext' => __DIR__ . '/..' . '/opis/closure/src/ClosureContext.php',
        'Opis\\Closure\\ClosureScope' => __DIR__ . '/..' . '/opis/closure/src/ClosureScope.php',
        'Opis\\Closure\\ClosureStream' => __DIR__ . '/..' . '/opis/closure/src/ClosureStream.php',
        'Opis\\Closure\\ISecurityProvider' => __DIR__ . '/..' . '/opis/closure/src/ISecurityProvider.php',
        'Opis\\Closure\\ReflectionClosure' => __DIR__ . '/..' . '/opis/closure/src/ReflectionClosure.php',
        'Opis\\Closure\\SecurityException' => __DIR__ . '/..' . '/opis/closure/src/SecurityException.php',
        'Opis\\Closure\\SecurityProvider' => __DIR__ . '/..' . '/opis/closure/src/SecurityProvider.php',
        'Opis\\Closure\\SelfReference' => __DIR__ . '/..' . '/opis/closure/src/SelfReference.php',
        'Opis\\Closure\\SerializableClosure' => __DIR__ . '/..' . '/opis/closure/src/SerializableClosure.php',
        'Te\\Client' => __DIR__ . '/../..' . '/src/Client.php',
        'Te\\Event\\Epoll' => __DIR__ . '/../..' . '/src/Event/Epoll.php',
        'Te\\Event\\Event' => __DIR__ . '/../..' . '/src/Event/Event.php',
        'Te\\Event\\Select' => __DIR__ . '/../..' . '/src/Event/Select.php',
        'Te\\Protocols\\Protocol' => __DIR__ . '/../..' . '/src/Protocols/Protocol.php',
        'Te\\Protocols\\Stream' => __DIR__ . '/../..' . '/src/Protocols/Stream.php',
        'Te\\Protocols\\Text' => __DIR__ . '/../..' . '/src/Protocols/Text.php',
        'Te\\Server' => __DIR__ . '/../..' . '/src/Server.php',
        'Te\\TcpConnection' => __DIR__ . '/../..' . '/src/TcpConnection.php',
        'Te\\UdpConnection' => __DIR__ . '/../..' . '/src/UdpConnection.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit522b94383911930808795263f051f99a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit522b94383911930808795263f051f99a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit522b94383911930808795263f051f99a::$classMap;

        }, null, ClassLoader::class);
    }
}
