<?php
/**
 * Mailgun email gateway plugin.
 *
 *	Config example (yml):
 *		settings:
 *		  mailgun:
 *		    enabled: true
 *		    domain: <mailgun-domain>
 *		    api_key: <mailgun-api-key>
 */
 
bind('emails', 'post', function ($event, $model)
{
	$settings = get("/settings/mailgun");
	
	// Mailgun enabled?
	if ($settings['enabled'])
	{	
		try {
			// Process mailgun post message.
			fwd_mailgun_send($event['data'], $settings);
			
			// Indicate gateway for logs.
			$event['data']['gateway'] = 'mailgun';
		}
		catch (Exception $e)
		{
			$model->error($e->getMessage());
			return false;
		}
		
		return bind_stop(true);
	}
});

/**
 * Post message to the mailgun API.
 */
function fwd_mailgun_send ($data, $settings)
{
	// Use type as message tag.
	if ($data['type'])
	{
		$data['o:tag'] = $data['type'];
	}
	
	// Filter post data.
	$allowed_fields = array(
		'from',
		'to',
		'cc',
		'bcc',
		'subject',
		'text',
		'html',
		'o:tag'
	);
	$post = array();
	foreach ($data as $key => $val)
	{
		if (in_array($key, $allowed_fields))
		{
			$post[$key] = $val;
		}
	}

	// Initiate request.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://api.mailgun.net/v2/'.$settings['domain'].'/messages');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query((array)$post));
	curl_setopt($ch, CURLOPT_USERPWD, 'api:'.$settings['api_key']);  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
	// Decode result (JSON).
	$result = json_decode(curl_exec($ch));
	$result_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	
	// Success?
	if ($result_code == 200)
	{
		$response = array(
			'message' => $result->message,
			'fields' => $post
		);
	}
	else
	{
		// Bad response.
		throw new Exception($result->message);
	}
	
	return $response;
}
