<?php
class PathConfig {
    // Configurações para subdomínio (raiz)
    const BASE_URL = '/caixa-seguro-7xy3q9kkle';
    const BASE_DIR = __DIR__ . '/../';
    
    // URLs públicas (para navegador)
    public static function url($path = '') {
        return self::BASE_URL . '/' . ltrim($path, '/');
    }
    
    public static function api($endpoint = '') {
        return self::url('api/' . ltrim($endpoint, '/'));
    }
    
    public static function modules($module = '') {
        return self::url('modules/' . ltrim($module, '/'));
    }
    
    // Caminhos físicos no servidor
    public static function root($path = '') {
        return self::BASE_DIR . ltrim($path, '/');
    }
    
    public static function config($file = '') {
        return self::root('config/' . ltrim($file, '/'));
    }
    
    public static function modules_dir($module = '') {
        return self::root('modules/' . ltrim($module, '/'));
    }
}
?>