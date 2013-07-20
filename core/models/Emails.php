<?php
/**
 * Email model.
 *
 * Copyright 2012 Forward.
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 */
class Emails extends AppModel
{
	/**
	 * Model definition.
	 */
	function define ()
	{
		// Fields.
		$this->fields = array(
			'id',
			'type',
			'to',
			'cc',
			'bcc',
			'from',
			'subject',
			'text',
			'html',
			'date_created'
		);
		$this->search_fields = array(
			'to',
			'subject'
		);
		
		// Validate.
		$this->validate = array(
			'required' => array(
				'type',
				'to',
				'from',
				'subject',
				'text'
			)
		);
		
		// Indexes.
		$this->indexes = array(
			'id' => 'unique',
			'type'
		);
		
		// Event binds.
		$this->binds = array(
		
			// POST email.
			'POST' => function ($event, $model)
			{
				$data =& $event['data'];
				
				// Id as email type.
				$data['type'] = $data['type'] ?: $event['id'];
				unset($event['id']);
				
				// Prepare data for email message.
				$data = Emails::prepare_post_data($data);
				
				if (!$model->validate($data))
				{
					return false;
					// Trigger special send event.
					/*if (false === trigger('emails', 'send', $data, $model))
					{
						return false;
					}*/
				}
			},
		);
		
		// Default send event.
		$this->bind('POST', function ($event, $model)
		{
			try {
				Emails::send_default($event['data']);
				
				// Indicate default mail gateway.
				$event['data']['gateway'] = 'default';
			}
			catch (Exception $e)
			{
				$model->error($e->getMessage());
				return false;
			}
			
			return true;
		},
			2 // Occurs after level 1 binds.
		);
	}
	
	/**
	 * Prepare data to post an email message.
	 */
	static function prepare_post_data ($data)
	{
		$settings = get("/settings/emails");
		
		// Merge default email settings.
		if (is_array($params = $settings[$data['type']]))
		{
			$data = array_merge($params, (array)$data);
		}
		
		// Remember original request params?
		if (Request::$controller->request)
		{
			$orig_to = Request::$controller->request->to;
			$orig_cc = Request::$controller->request->cc;
			$orig_bcc = Request::$controller->request->bcc;
			$orig_from = Request::$controller->request->from;
			$orig_subject = Request::$controller->request->subject;
		}
		
		// Render text content.
		if ($data['text'])
		{
			ob_start();
			$data['view'] = $data['text'];
			render($data);
			$data['text'] = ob_get_clean();
		}
		elseif ($data['raw_text'])
		{
			$data['text'] = $data['raw_text'];
		}
		
		// Render html content.
		if ($data['html'])
		{
			ob_start();
			$data['view'] = $data['html'];
			render($data);
			$data['html'] = ob_get_clean();
		}
		elseif ($data['raw_html'])
		{
			$data['html'] = $data['raw_html'];
		}
		
		unset($data['view']);
		unset($data['raw_text']);
		unset($data['raw_html']);
		
		// Restore request params?
		if (Request::$controller->request)
		{
			$data['to'] = $data['to'] ?: Request::$controller->request->to;
			$data['cc'] = $data['cc'] ?: Request::$controller->request->cc;
			$data['bcc'] = $data['bcc'] ?: Request::$controller->request->bcc;
			$data['from'] = $data['from'] ?: Request::$controller->request->from;
			$data['subject'] = $data['subject'] ?: Request::$controller->request->subject;
			
			Request::$controller->request->to = $orig_to;
			Request::$controller->request->cc = $orig_cc;
			Request::$controller->request->bcc = $orig_bcc;
			Request::$controller->request->from = $orig_from;
			Request::$controller->request->subject = $orig_subject;
		}
		
		// Filter out model resources.
		foreach ($data as $key => $val)
		{
			if ($data[$key] instanceof ModelResource)
			{
				unset($data[$key]);
			}
		}
		
		return $data;
	}
	
	/**
	 * Send multipart message using default mail function.
	 */
	static function send_default ($data)
	{
		// Multipart boundary.
		$boundary = md5(time());

		// Message headers.
		$headers = "From: {$data['from']}\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

		// Message content.
		$message = "This is a MIME encoded message.\r\n";
		$message .= "\r\n\r\n--{$boundary}\r\n";
		$message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
		$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
		$message .= $data['text'];
		
		// Attach html content?
		if ($data['html'])
		{
			$message .= "\r\n\r\n--{$boundary}\r\n";
			$message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
			$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
			$message .= $data['html'];
		}
		
		$message .= "\r\n\r\n--{$boundary}--";
		
		// Send!
		mail($data['to'], $data['subject'], $message, $headers, '-f '.$data['from']);
		
		return true;
	}
}