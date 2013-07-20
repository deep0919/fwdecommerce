<?php
/**
 * FedEx shipping service plugin.
 *
 *	Config example (yml):
 *		settings:
 *		  shipments:
 *		    fedex:
 *		      enabled: true
 *		      key: <fedex-key>
 *		      password: <fedex-password>
 *		      meter: <fedex-meter>
 *		      account: <fedex-account>
 *		      markup: 10%
 *		      shippers:
 *		        -
 *		          city: <shipping-from-city>
 *		          state: <shipping-from-state>
 *		          zip: <shipping-from-zip>
 *		          country: <shipping-from-country>
 *		      methods:
 *		        FEDEX_GROUND: true #FedEx Ground
 *		        FEDEX_2_DAY: true  #FedEx 2-Day Air
 *		        ...
 */
 
bind('shipments', 'methods', function ($methods, $params, $model)
{
	// Get FedEx shipment settings.
	$settings = get("/settings/shipments/fedex");
	
	// Is FedEx enabled? Are methods configured?
	if ($settings['enabled'] && $settings['methods'])
	{
		try {
			// Process FedEx service rating.
			$fedex_methods = fwd_fedex_rates($params, $settings);
			
			// Merge with existing methods.
			$methods = array_merge((array)$methods, $fedex_methods);
		}
		catch (Exception $e)
		{
			$model->error($e->getMessage(), 'fedex');
			return false;
		}
	}
	
	// Return combined methods.
	return $methods;
});

bind('shipments', 'after:get', function ($result, $event, $model)
{
	if (strcasecmp($result['carrier'], 'FedEx') == 0)
	{
		$result['tracking_info'] = fwd_fedex_tracking_info($result);
	}
});

/**
 * Get FedEx rate quotes.
 */
function fwd_fedex_rates ($params, $settings)
{
	// Configurable FedEx service methods.
	$service_list = array(
		'FEDEX_GROUND' => 'Fedex Ground',
		'FEDEX_EXPRESS_SAVER' => 'Fedex Express Saver',
		'FEDEX_2_DAY' => 'Fedex 2-Day Air',
		'FEDEX_2_DAY_AM' => 'Fedex 2-Day Air A.M.',
		'STANDARD_OVERNIGHT' => 'Fedex Standard Overnight',
		'PRIORITY_OVERNIGHT' => 'Fedex Priority Overnight',
		'FIRST_OVERNIGHT' => 'Fedex First Overnight',
		'INTERNATIONAL_PRIORITY' => 'Fedex Priority International'
	);
	
	// If params are empty, return available methods only.
	if (empty($params))
	{
		$methods = array();
		foreach ((array)$settings['methods'] as $key => $method)
		{
			if ($method)
			{
				$method = is_array($method) ? $method : array();
				$methods[] = array(
					'id' => $key,
					'name' => $method['name'] ?: $service_list[$key]
				);
			}
		}
		
		return $methods;
	}
	
	// Get shipper, default or specific.
	$shipper = array_merge(
		array(
			'package_weight' => 100
		),
		(array)$settings['shippers'][0],
		(array)$settings['shippers'][$params['shipper']]
	);
	
	// Disable WSDL caching.
	ini_set('soap.wsdl_cache_enabled', '0');
	
	// Create SOAP client with Fedex RateService WSDL.
	$client = new SoapClient(dirname(__FILE__).'/wsdl/RateService_v10.wsdl', array('trace' => 1));
	
	// Set endpoint host?
	if ($settings['host'])
	{
		$client->__setLocation($settings['host']);
	}
	
	// Split package by weight limit?
	if ($shipper['package_weight'] && $params['weight'] > $shipper['package_weight'])
	{
		$num_packages = ceil($params['weight'] / $shipper['package_weight']);
		for ($i = 0; $i < $num_packages; $i++)
		{
			if ($i >= 200) break;
			
			$params['packages'][$i] = array(
				'insurance_amount' => $params['insurance_amount']/$num_packages,
				'insurance_currency' => $params['insurance_currency'],
				'weight' => $params['weight']/$num_packages,
				'units' => $params['units']
			);
		}
	}
	
	// Setup request.
	$request = array(
		'WebAuthenticationDetail' => array(
			'UserCredential' => array(
				'Key' => $settings['key'],
				'Password' => $settings['password']
			)
		),
		'ClientDetail' => array(
			'AccountNumber' => $settings['account'],
			'MeterNumber' => $settings['meter']
		),
		'TransactionDetail' => array(
			'CustomerTransactionId' => '*** Rate Request v10 using PHP ***'
		),
		'Version' => array(
			'ServiceId' => 'crs',
			'Major' => '10',
			'Intermediate' => '0',
			'Minor' => '0'
		),
		'ReturnTransitAndCommit' => true,
		'RequestedShipment' => array(
			'DropoffType' => 'REGULAR_PICKUP',
			'ShipTimestamp' => date('c'),
			'PackagingType' => 'YOUR_PACKAGING',
			'Shipper' => array(
				'Address' => array(
					'StreetLines' => array($shipper['address']),
					'City' => $shipper['city'],
					'StateOrProvinceCode' => $shipper['state'],
					'PostalCode' => $shipper['zip'],
					'CountryCode' => $shipper['country'] ?: 'US'
				)
			),
			'Recipient' => array(
				'Address' => array (
					'StreetLines' => array($params['address']),
					'City' => $params['city'],
					'StateOrProvinceCode' => $params['state'],
					'PostalCode' => $params['zip'],
					'CountryCode' => $params['country'] ?: 'US'
				)
			),
			'ShippingChargesPayment' => array(
				'PaymentType' => 'SENDER',
				'Payor' => array(
					'AccountNumber' => $shipper['account'] ?: $settings['account'],
					'CountryCode' => $shipper['country'] ?: 'US'
				)
			),
			'RateRequestTypes' => 'ACCOUNT',
			'RateRequestTypes' => 'LIST',
			'PackageCount' => $params['packages'] ? count($params['packages']) : '1',
			
			// Appended below.
			'RequestedPackages' => array()
		)
	);
	
	// Default single package?
	if (count($params['packages']) == 0)
	{
		$params['packages'][0] = array(
			'insurance_amount' => $params['insurance_amount'],
			'insurance_currency' => $params['insurance_currency'],
			'weight' => $params['weight'] ?: 1,
			'units' => $params['units']
		);
		
		if ($params['dimensions'])
		{
			$params['packages'][0]['dimensions'] = array(
				'length' => $params['dimensions']['length'],
				'width' => $params['dimensions']['width'],
				'height' => $params['dimensions']['height'],
				'units' => $params['dimensions']['units'],
			);
		}
	}
	
	// Add package(s) to request.
	foreach ($params['packages'] as $seq => $package)
	{
		$request['RequestedShipment']['RequestedPackageLineItems'][$seq] = array(
			'SequenceNumber' => $seq + 1,
			'GroupPackageCount' => 1,
			'InsuredValue' => array(
				'Amount' => $package['insurance_amount'] ?: 0,
				'Currency' => $package['insurance_currency'] ?: 'USD'
			),
			'Weight' => array(
				'Value' => $package['weight'],
				'Units' => $package['units'] ?: 'LB'
			)
		);
		
		// Dimensions?
		if ($package['dimensions'])
		{
			$request['RequestedShipment']['RequestedPackageLineItems'][$seq]['Dimensions'] = array(
				'Length' => $package['dimensions']['length'],
				'Width' => $package['dimensions']['width'],
				'Height' => $package['dimensions']['height'],
				'Units' => $package['dimensions']['units'] ?: 'IN',
			);
		}
	}
	
	// Try the request.
	try 
	{
		$response = $client->getRates($request);
		
		if ($response->HighestSeverity != 'FAILURE' && $response->HighestSeverity != 'ERROR')
		{
			if (!is_array($response->RateReplyDetails))
			{
				$response->RateReplyDetails = array($response->RateReplyDetails);
			}
			foreach ($response->RateReplyDetails as $RateReplyDetail)
			{
				$service_type = $RateReplyDetail->ServiceType;
				
				if ($service_list[$service_type])
				{
					$rsd = end($RateReplyDetail->RatedShipmentDetails);
					$tnc = $rsd->ShipmentRateDetail->TotalNetCharge;
					$price = (float)preg_replace('/[^\d\.]/i', '', $tnc->Amount);
					
					// Markup?
					if ($settings['markup'])
					{
						$price = Discounts::apply_value($price, '+'.$settings['markup']);
					}
					
					// Discount?
					if ($settings['discount'])
					{
						$price = Discounts::apply_value($price, $settings['markup']);
					}
					
					// Rated method configured/available?
					if ($method = $settings['methods'][$service_type])
					{
						$method = is_array($method) ? $method : array();
						
						$service_name = $method['name'] ?: $service_list[$service_type];
						$service_price = round($price , 2);
					
						$shipment_rates[] = array(
							'id' => $service_type,
							'name' => $service_name,
							'price' => $service_price
						);
					}
				}
			}
		}
		elseif ($response->HighestSeverity == 'FAILURE' || $response->HighestSeverity == 'ERROR')
		{
			throw new Exception($response->Notifications->LocalizedMessage ?: $response->Notifications->Message);
		}
	}
	catch (SoapFault $e) 
	{
	   throw new Exception($e->getMessage());
	}

	return $shipment_rates;
}

/**
 * Get shipment tracking info.
 */
function fwd_fedex_tracking_info ($shipment)
{
	$tracking_info = array();
	
	// Clean tracking number.
	$tracking_info['number'] = preg_replace('/[\s]/', '', $shipment['tracking']);

	// Tracking website URL.
	$tracking_info['url'] = 'http://www.fedex.com/Tracking?tracknumber_list='.$tracking_info['number'];
	
	return $tracking_info;
}