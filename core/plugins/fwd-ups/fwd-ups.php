<?php
/**
 * UPS shipping service plugin.
 *
 *	Config example (yml):
 *		settings:
 *		  shipments:
 *		    ups:
 *		      enabled: true
 *		      license: <ups-license>
 *		      login: <ups-login>
 *		      password: <ups-password>
 *		      markup: 10%
 *		      shippers:
 *		        -
 *		          account: <ups-account>
 *		          city: <shipping-from-city>
 *		          state: <shipping-from-state>
 *		          zip: <shipping-from-zip>
 *		          country: <shipping-from-country>
 *		      methods:
 *		        01: true  #UPS Next Day Air
 *		        02: false #UPS Second Day Air
 *		        03: true  #UPS Ground
 *		        ...
 */
 
bind('shipments', 'methods', function ($methods, $params, $model)
{
	// Get UPS shipment settings.
	$settings = get("/settings/shipments/ups");
	
	// Is UPS enabled? Are methods configured?
	if ($settings['enabled'] && $settings['methods'])
	{
		try {
			// Process UPS service rating.
			$ups_methods = fwd_ups_rates($params, $settings);
			
			// Merge with existing methods.
			$methods = array_merge((array)$methods, $ups_methods);
		}
		catch (Exception $e)
		{
			$model->error($e->getMessage(), 'ups');
			return false;
		}
	}
	
	// Return combined methods.
	return $methods;
});

bind('shipments', 'after:get', function ($result, $event, $model)
{
	if (strcasecmp($result['carrier'], 'UPS') == 0)
	{
		$result['tracking_info'] = fwd_ups_tracking_info($result);
	}
});

/**
 * Get UPS rate quotes.
 */
function fwd_ups_rates ($params, $settings)
{
	// Configurable UPS service methods.
	$service_list = array(
		'01' => 'UPS Next Day Air',
		'02' => 'UPS Second Day Air',
		'03' => 'UPS Ground',
		'11' => 'UPS Standard',
		'12' => 'UPS Three-Day Select',
		'13' => 'UPS Next Day Air Saver',
		'65' => 'UPS Worldwide Saver',
		'07' => 'UPS Worldwide Express',
		'08' => 'UPS Worldwide Expedited',
		'14' => 'UPS Next Day Air Early A.M.',
		'54' => 'UPS Worldwide Express Plus',
		'59' => 'UPS Second Day Air A.M.'
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

	// Get shipper, default or specific settings.
	$shipper = array_merge(
		array(
			'package_weight' => 100
		),
		(array)$settings['shippers'][0],
		(array)$settings['shippers'][$params['shipper']]
	);
	
	// Split package by weight limit?
	if ($shipper['package_weight'] && $params['weight'] > $shipper['package_weight'])
	{
		$num_packages = ceil($params['weight'] / $shipper['package_weight']);
		
		for ($i = 0; $i < $num_packages; $i++)
		{
			if ($i >= 200) break;
			
			$params['packages'][$i] = array(
				'weight' => $params['weight']/$num_packages,
				'units' => $params['units']
			);
		}
	}
	else
	{
		$params['packages'][1] = array(
			'weight' => $params['weight'] ?: 1,
			'units' => $params['units']
		);
	}

	// Add package(s) to request.
	foreach ($params['packages'] as $package)
	{	
		// Dimensions?
		if ($package['dimensions'])
		{
			$package_dimensions = "
				<Dimensions>
					<Length>{$package['dimensions']['length']}</Length>
					<Width>{$package['dimensions']['width']}</Width>
					<Height>{$package['dimensions']['height']}</Height>
					<UnitOfMeasurement>
						<Code>".($package['dimensions']['units'] ?: 'IN')."</Code>
					</UnitOfMeasurement>
				</Dimensions>
			";
		}
		else
		{
			$package_dimensions = null;
		}

		$request_packages .= "
			<Package>
				<PackagingType>
					<Code>02</Code>
				</PackagingType>
				<PackageWeight>
					<UnitOfMeasurement>
						<Code>".($package['units'] ? "{$package['units']}S" : 'LBS')."</Code>
					</UnitOfMeasurement>
					<Weight>{$package['weight']}</Weight>
				</PackageWeight>
				{$package_dimensions}
			</Package>
		";
	}

	// XML API call.
	$request = "
		<?xml version=\"1.0\" ?>
		<AccessRequest xml:lang='en-US'>
			<AccessLicenseNumber>{$settings['license']}</AccessLicenseNumber>
			<UserId>{$settings['login']}</UserId>
			<Password>{$settings['password']}</Password>
		</AccessRequest>
		<?xml version=\"1.0\" ?>
		<RatingServiceSelectionRequest>
			<Request>
				<RequestAction>Rate</RequestAction>
				<RequestOption>Shop</RequestOption>
			</Request>
			<PickupType>
				<Code>01</Code>
			</PickupType>

			<Shipment>
				<Shipper>
					<ShipperNumber>".($shipper['account'] ?: $settings['account'])."</ShipperNumber>
					<Address>
						<City>{$shipper['city']}</City>
						<PostalCode>{$shipper['zip']}</PostalCode>
						<CountryCode>".($shipper['country'] ?: 'US')."</CountryCode>
					</Address>
				</Shipper>

				<ShipTo>
					<Address>
						<City>{$params['city']}</City>
						<StateProvinceCode>{$params['state']}</StateProvinceCode>
						<PostalCode>{$params['zip']}</PostalCode>
						<CountryCode>".($params['country'] ?: 'US')."</CountryCode>
						<ResidentialAddressIndicator/>
					</Address>
				</ShipTo>

				{$request_packages}

			</Shipment>

		</RatingServiceSelectionRequest>
	";

	// Initialize connection with UPS service.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $settings['host'] ?: "https://wwwcie.ups.com/ups.app/xml/Rate");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($ch);
	curl_close($ch);

	if ($response)
	{
		$doc = new SimpleXMLElement($response);
		$response_code = (string)$doc->Response[0]->ResponseStatusCode;
	}
	switch ($response_code)
	{
		// Good response.
		case 1:
			foreach ($doc->RatedShipment as $shipment)
			{
				if ($service_type = (string)$shipment->Service[0]->Code)
				{
					$price = (float)$shipment->TotalCharges[0]->MonetaryValue;
					
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
			break;

		// Bad response.
		case 0:
			throw new Exception((string)$doc->Response[0]->Error[0]->ErrorDescription[0]);
			break;

		// No response?
		default:
			throw new Exception("Unknown response from UPS Rating Service");
	}

	return $shipment_rates;
}

/**
 * Get shipment tracking info.
 */
function fwd_ups_tracking_info ($shipment)
{
	$tracking_info = array();
	
	// Clean tracking number.
	$tracking_info['number'] = preg_replace('/[\s]/', '', $shipment['tracking']);

	// Tracking website URL.
	$tracking_info['url'] = 'http://wwwapps.ups.com/WebTracking/processInputRequest?tracknum='.$tracking_info['number'];
	
	return $tracking_info;
}