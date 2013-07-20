<?php
/**
 * Memcache backend plugin.
 * 
 *	Config example (yml):
 *		cache:
 *		  enabled: true
 *		  backend: memcache
 *		  host: <memcache-host>
 *		  port: <memcache-port>
 *        weight: <memcache-weight>
 *	OR
 *		cache:
 *		  enabled: true
 *		  backend: memcache
 *        servers:
 *          - #server 1
 *		      host: <memcache-host>
 *		      port: <memcache-port>
 *            weight: <memcache-weight>
 *          - #server 2
 *		      ...
 */

bind('cache', 'get', function ($result, $id)
{
	if (!$id || !$memcache = fwd_memcache_connect()) return;
	
	return $memcache->get($id);
});

bind('cache', 'put, post', function ($result, $id, $query)
{
	if (!$id || !$memcache = fwd_memcache_connect()) return;
	
	// Expire time?
	if (is_numeric($query['expire']))
	{
		$expire = (int)$query['expire'];
	}
	
	return $memcache->set($id, $result, $expire ?: 0) ? $result : false;
});

bind('cache', 'delete', function ($result, $id)
{
	if (!$id || !$memcache = fwd_memcache_connect()) return;

	$value = $memcache->get($id);
	
	$target =& $value;
	foreach ((array)$stack as $index)
	{
		if (!is_array($target))
		{
			$target = array($target);
		}
		$target =& $target[$index];
	}
	
	$target = null;
	
	return $memcache->set($id, $value);
});

function fwd_memcache_connect ()
{
	static $memcache;
	static $connected;
	
	// Already connected?
	if ($connected)
	{
		return $memcache;
	}
	
	$settings = Request::$config->cache;
	
	// Memcache enabled?
	if ($settings['backend'] != 'memcache' || !$settings['enabled'])
	{
		return false;
	}
	
	// Memcached installed?
	if (!class_exists('Memcached'))
	{
		throw new Exception('Memcached is not installed');
	}
	
	// Setup memcache.
	$memcache = $memcache ?: new Memcached;
	
	// One or many memcache servers.
	$servers = is_array($settings['servers']) ? $settings['servers'] : array($settings);
	
	// Connect to memcache server(s).
	foreach ($servers as $values)
	{
		if (is_array($values) && $values['enabled'] !== false)
		{
			// Connection params.
			$host = $values['host'] ?: 'localhost';
			$port = $values['port'] ?: '11211';
			$weight = $values['weight'] ?: 0;

			// Add server to connection pool.
			$connected = $memcache->addServer($host, $port, $weight);
		}
	}
	
	return $memcache;
}

