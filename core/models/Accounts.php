<?php
/**
 * Account model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Accounts extends AppModel
{
	function define ()
	{
		// Fields.
		$this->fields = array(
			'id',
			'name',
			'email',
			'password',
			'roles',
			'ip_address',
			'date_created',
			'date_updated',
			'first_name' => function ($account)
			{
				return Accounts::get_first_name($account);
			},
			'last_name' => function ($account)
			{
				return Accounts::get_last_name($account);
			},
			'orders' => function ($account)
			{
				return get("/orders", array('account_id' => $account['id']));
			},
			'billing' => function ($account)
			{
				return $account['billing'] ?: $account['last_order']['billing'];
			},
			'shipping' => function ($account)
			{
				return $account['shipping'] ?: $account['last_order']['shipping'];
			},
			'order_count' => function ($account)
			{
				return get("/orders", array(':count' => true, 'account_id' => $account['id']));
			},
			'last_order' => function ($account)
			{
				return Accounts::get_last_complete_order($account);
			},
			'credits' => function ($account)
			{
				return get("/payments", array('method' => 'credit', 'account_id' => $account['id'], 'limit' => null, 'order' => 'date_created ASC'));
			},
			'balance' => function ($account)
			{
				return Accounts::get_balance($account);
			},
			'has_role' => function ($account)
			{
				foreach ((array)$account['roles'] as $key => $val)
				{
					$roles[(is_string($val) ? $val : $key)] = true;
				}
				return $roles;
			},
			'discounts' => function ($account)
			{
				return get("/discounts", array('account_role' => $account['roles'], 'account_id' => $account['id'], 'is_valid' => true));
			}
		);
		
		// Search fields.
		$this->search_fields = array(
			'id',
			'name',
			'email'
		);
		
		// Email slug.
		$this->slug_pk = 'email';
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique',
			'email' => 'unique'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'name',
				'email',
				'password'
			),
			'email-address' => array(
				'email'
			),
			'unique' => array(
				'email'
			),
			'length' => array(
				'password' => array(
					'min' => 4
				)
			)
		);
		
		// Event binds.
		$this->binds = array(
			
			// GET account.
			'GET' => function ($event, $model)
			{
				$params =& $event['data'];
				
				// E-mail slugs need to be specially url decoded.
				if ($event['id'] && !is_numeric($event['id']))
				{
					$event['id'] = urldecode($event['id']);
					$event['id'] = strtolower($event['id']);
					$event['id'] = str_replace(' ', '+', trim($event['id']));
				}
				
				// E-mails are case-insensitive.
				if ($params['email'])
				{
					$params['email'] = strtolower($params['email']);
				}
				
				// Approve login?
				if ($params['login'])
				{
					return $model->login($params['login'], $params['role']);
				}
			},

			// POST account.
			'POST' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Default name.
				if (!isset($data['name']) && isset($data['email']))
				{
					list($name) = explode('@', $data['email']);
					$name = preg_replace('/[^\w]/', ' ', $name);
					$data['name'] = ucwords($name);
				}
				
				// Auto hash password?
				if ($data['password'])
				{
					$data['password'] = $model->hash_password($data['password']);
				}
				// Already hashed?
				elseif ($data['password_hash'])
				{
					$data['password'] = $data['password_hash'];
					unset($data['password_hash']);
				}
			},
			
			// PUT account.
			'PUT' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Update existing?
				if ($account = $model->get($event['id']))
				{
					// Update default billing?
					if ($data['billing'])
					{
						$data['billing'] = array_merge(
							(array)$account['billing'],
							(array)$data['billing']
						);
						
						$data['billing']['default'] = true;
					}
					
					// Update default shipping?
					if ($data['shipping'])
					{
						$data['shipping'] = array_merge(
							(array)$account['shipping'],
							(array)$data['shipping']
						);
						
						$data['shipping']['default'] = true;
					}
					
					// Reset password?
					if ($data['password_reset'])
					{
						$data['password'] = $model->hash_password($data['password_reset']);
						unset($data['password_reset']);
					}
					// Already hashed?
					elseif ($data['password_hash'])
					{
						$data['password'] = $data['password_hash'];
						unset($data['password_hash']);
					}
					else
					{
						// Password can't be updated arbitrarily.
						unset($data['password']);
					}
				}
			},
			
			// POST or PUT account.
			'POST, PUT' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// E-mails are case-insensitive.
				if ($data['email'])
				{
					$data['email'] = strtolower($data['email']);
				}

				// Add role?
				if ($data['role'])
				{
					$data['roles'][] = $data['role'];
					$data['roles'] = array_unique($data['roles']);
					unset($data['role']);
				}
				
				// Create auth token?
				if ($data[':auth'])
				{
					$data['auth_token'] = md5(time().$data['email']);
				}
			},
			
			// After PUT, POST account.
			'after:POST, after:PUT' => function ($result, $event)
			{
				if ($event['data'][':auth'] && $result['auth_token'])
				{
					$settings = get("/settings/emails/auth");
					
					if ($settings['auth'] !== false)
					{
						post("/emails/auth", array(
							'account' => $result,
							'to' => $result['email'],
							'from' => $settings['from'],
							'subject' => $settings['subject'],
							'html' => $settings['html'],
							'text' => $settings['text']
						));
					}
				}
			}
		);
	}
	
	/**
	 * Attempt login with email/password.
	 */
	function login ($params, $role = null)
	{
		// Find account by email or id?
		if ($id = $params['email'] ?: $params['id'])
		{
			$account = get("/accounts/{$id}", array('is_deleted' => null));
		}
		else
		{
			$this->error('required', 'email');
			return false;
		}
		
		// Account found?
		if ($account)
		{
			// Correct role?
			if (!$this->has_role($account, $role))
			{
				$this->error("not authorized ({$role})", 'email');
				return false;
			}
			
			// Correct password?
			if ($params['password'] && ($account['password'] == $this->hash_password($params['password'], $account['password'])))
			{
				// Update IP address (without changing date updated).
				put("/accounts/{$account['id']}", array(
					'ip_address' => $_SERVER['REMOTE_ADDR'] ? : $account['ip_address'],
					'date_updated' => $account['date_updated']
				));

				// Good login.
				return $account;
			}
			else
			{
				$this->error('incorrect', 'password');
			}
		}
		else
		{
			$this->error('not found', 'email');
		}

		// Bad login.
		return false;
	}
	
	/**
	 * Create hash from password
	 */
	function hash_password ($password, $salt = null)
	{
		// Validate password?
		if ($this && $this->validate)
		{
			$valid = array(
				'password' => $password
			);
			if (!$this->validate($valid))
			{
				return '';
			}
		}
		if (empty($password))
		{
			return '';
		}
		 
		// Generate salt?
		if (empty($salt))
		{
			// Blowfish algorithm.
			$salt = Request::$config->app['crypt_blowfish'] ?: '$2a$07$';
			
			// Append random 22 char string.
			// Note: last 4 bits truncated from 132 to 128.
			$salt .= substr(sha1(mt_rand()), 0, 22);
		}
		
		// Hash the password.
		return crypt($password, $salt);
	}
	
	/**
	 * Account has a certain role?
	 */
	function has_role ($account, $role)
	{
		if ($role && !in_array($role, (array)$account['roles']))
		{
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get first name.
	 */
	function get_first_name ($account)
	{
		$parts = explode(' ', $account['name']);
		return $parts[0];
	}
	
	/**
	 * Get last name.
	 */
	function get_last_name ($account)
	{
		$parts = explode(' ', $account['name']);
		array_shift($parts);
		return implode(' ', $parts);
	}
	
	/**
	 * Get credit balance on account.
	 */
	function get_balance ($account)
	{
		$balance = 0;
		foreach ((array)$account['credits'] as $credit)
		{
			$balance += -($credit['amount']);
		}
		
		return $balance;
	}
	
	/**
	 * Get last complete order by account.
	 */
	function get_last_complete_order ($account)
	{
		$last = get("/orders", array(
			'account_id' => $account['id'],
			'shipping' => array('$ne' => null),
			'billing' => array('$ne' => null),
			':last' => true
		));
		
		return $last;
	}
}
