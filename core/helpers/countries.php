<?php
/**
 * Returns a complete list of countries available
 * for display and validation purposes.
 *
 * May be overriden by "countries" setting.
 *
 *		Usage example:
 *			{foreach countries() as $country}
 *				{$country.code}
 *				{$country.name}
 *				{$country.states}
 *			{/foreach}
 *
 *			{$us = "US"|countries}
 *			{$us.name} # United States
 */
function countries ($params = null)
{
	// Country code or name.
	$country = is_array($params) ? $params['country'] : $params;
	
	// Get countries from settings?
	$countries = get("/settings/countries");
	
	// Default list?
	if (!$countries)
	{
		$countries = array(
			array(
				'code' => 'US',
				'name' => 'United States',
				'state_label' => 'State',
				'states' => array(
					'AL' => "Alabama",
					'AK' => "Alaska",
					'AZ' => "Arizona",
					'AR' => "Arkansas",
					'CA' => "California",
					'CO' => "Colorado",
					'CT' => "Connecticut",
					'DE' => "Delaware",
					'DC' => "District of Columbia",
					'FL' => "Florida",
					'GA' => "Georgia",
					'HI' => "Hawaii",
					'ID' => "Idaho",
					'IL' => "Illinois",
					'IN' => "Indiana",
					'IA' => "Iowa",
					'KS' => "Kansas",
					'KY' => "Kentucky",
					'LA' => "Louisiana",
					'ME' => "Maine",
					'MD' => "Maryland",
					'MA' => "Massachusetts",
					'MI' => "Michigan",
					'MN' => "Minnesota",
					'MS' => "Mississippi",
					'MO' => "Missouri",
					'MT' => "Montana",
					'NE' => "Nebraska",
					'NV' => "Nevada",
					'NH' => "New Hampshire",
					'NJ' => "New Jersey",
					'NM' => "New Mexico",
					'NY' => "New York",
					'NC' => "North Carolina",
					'ND' => "North Dakota",
					'OH' => "Ohio",
					'OK' => "Oklahoma",
					'OR' => "Oregon",
					'PA' => "Pennsylvania",
					'RI' => "Rhode Island",
					'SC' => "South Carolina",
					'SD' => "South Dakota",
					'TN' => "Tennessee",
					'TX' => "Texas",
					'UT' => "Utah",
					'VT' => "Vermont",
					'VA' => "Virginia",
					'WA' => "Washington",
					'WV' => "West Virginia",
					'WI' => "Wisconsin",
					'WY' => "Wyoming",
					// Territories
					'AS' => "American Samoa",
					'AA' => "Armed Forces Americas",
					'AE' => "Armed Forces Europe",
					'AP' => "Armed Forces Pacific",
					'FM' => "Federated States of Micronesia",
					'GU' => "Guam",
					'MH' => "Marshall Islands",
					'MP' => "Northern Mariana Islands",
					'PW' => "Palau",
					'PR' => "Puerto Rico",
					'VI' => "Virgin Islands"
				)
			),
			array(
				'code' => 'CA',
				'name' => 'Canada',
				'state_label' => 'Province',
				'states' => array(
					'AB' => "Alberta",
					'BC' => "British Columbia",
					'MB' => "Manitoba",
					'NB' => "New Brunswick",
					'NL' => "Newfoundland",
					'NT' => "Northwest Territories",
					'NS' => "Nova Scotia",
					'NU' => "Nunavut",
					'ON' => "Ontario",
					'PE' => "Prince Edward Island",
					'QC' => "Quebec",
					'SK' => "Saskatchewan",
					'YT' => "Yukon"
				)
			),
			array(
				'code' => 'AU',
				'name' => 'Australia',
				'state_label' => 'State/Territory',
				'states' => array(
					'ACT' => "Australian Capital Territory",
					'NSW' => "New South Wales",
					'NT'  => "Northern Territory",
					'QLD' => "Queensland",
					'SA'  => "South Australia",
					'TAS' => "Tasmania",
					'VIC' => "Victoria",
					'WA'  => "Western Australia"
				)
			),
			array(
				'code' => 'UK',
				'name' => 'United Kingdom'
			),
			array(
				'code' => 'AF',
				'name' => 'Afghanistan'
			),
			array(
				'code' => 'AX',
				'name' => 'Aland Islands'
			),
			array(
				'code' => 'AL',
				'name' => 'Albania'
			),
			array(
				'code' => 'DZ',
				'name' => 'Algeria'
			),
			array(
				'code' => 'AD',
				'name' => 'Andorra'
			),
			array(
				'code' => 'AO',
				'name' => 'Angola'
			),
			array(
				'code' => 'AI',
				'name' => 'Anguilla'
			),
			array(
				'code' => 'AG',
				'name' => 'Antigua And Barbuda'
			),
			array(
				'code' => 'AR',
				'name' => 'Argentina'
			),
			array(
				'code' => 'AM',
				'name' => 'Armenia'
			),
			array(
				'code' => 'AW',
				'name' => 'Aruba'
			),
			array(
				'code' => 'AT',
				'name' => 'Austria'
			),
			array(
				'code' => 'AZ',
				'name' => 'Azerbaijan'
			),
			array(
				'code' => 'BS',
				'name' => 'Bahamas'
			),
			array(
				'code' => 'BH',
				'name' => 'Bahrain'
			),
			array(
				'code' => 'BD',
				'name' => 'Bangladesh'
			),
			array(
				'code' => 'BB',
				'name' => 'Barbados'
			),
			array(
				'code' => 'BY',
				'name' => 'Belarus'
			),
			array(
				'code' => 'BE',
				'name' => 'Belgium'
			),
			array(
				'code' => 'BZ',
				'name' => 'Belize'
			),
			array(
				'code' => 'BJ',
				'name' => 'Benin'
			),
			array(
				'code' => 'BM',
				'name' => 'Bermuda'
			),
			array(
				'code' => 'BT',
				'name' => 'Bhutan'
			),
			array(
				'code' => 'BO',
				'name' => 'Bolivia'
			),
			array(
				'code' => 'BA',
				'name' => 'Bosnia And Herzegovina'
			),
			array(
				'code' => 'BW',
				'name' => 'Botswana'
			),
			array(
				'code' => 'BV',
				'name' => 'Bouvet Island'
			),
			array(
				'code' => 'BR',
				'name' => 'Brazil',
				'state_label' => 'State',
				'states' => array(
					'AC' => "Acre",
					'AL' => "Alagoas",
					'AP' => "Amapa",
					'AM' => "Amazonas",
					'BA' => "Bahia",
					'CE' => "Ceara",
					'DF' => "Distrito Federal",
					'ES' => "Espirito Santo",
					'GO' => "Goias;",
					'MA' => "Maranhao",
					'MT' => "Mato Grosso",
					'MS' => "Mato Grosso do Sul",
					'MG' => "Minas Gerais",
					'PA' => "Para;",
					'PB' => "Paraiba",
					'PR' => "Parana",
					'PE' => "Pernambuco",
					'PI' => "Piaui",
					'RJ' => "Rio de Janeiro",
					'RN' => "Rio Grande do Norte",
					'RS' => "Rio Grande do Sul",
					'RO' => "Rondonia",
					'RR' => "Roraima",
					'SC' => "Santa Catarina",
					'SP' => "Sao Paulo",
					'SE' => "Sergipe",
					'TO' => "Tocantins"
				)
			),
			array(
				'code' => 'IO',
				'name' => 'British Indian Ocean Territory'
			),
			array(
				'code' => 'BN',
				'name' => 'Brunei'
			),
			array(
				'code' => 'BG',
				'name' => 'Bulgaria'
			),
			array(
				'code' => 'BF',
				'name' => 'Burkina Faso'
			),
			array(
				'code' => 'BU',
				'name' => 'Burma'
			),
			array(
				'code' => 'BI',
				'name' => 'Burundi'
			),
			array(
				'code' => 'KH',
				'name' => 'Cambodia'
			),
			array(
				'code' => 'CM',
				'name' => 'Cameroon'
			),
			array(
				'code' => 'CV',
				'name' => 'Cape Verde'
			),
			array(
				'code' => 'KY',
				'name' => 'Cayman Islands'
			),
			array(
				'code' => 'CF',
				'name' => 'Central African Republic'
			),
			array(
				'code' => 'TD',
				'name' => 'Chad'
			),
			array(
				'code' => 'CL',
				'name' => 'Chile'
			),
			array(
				'code' => 'CN',
				'name' => 'China'
			),
			array(
				'code' => 'CX',
				'name' => 'Christmas Island'
			),
			array(
				'code' => 'CC',
				'name' => 'Cocos (Keeling) Islands'
			),
			array(
				'code' => 'CO',
				'name' => 'Colombia'
			),
			array(
				'code' => 'KM',
				'name' => 'Comoros'
			),
			array(
				'code' => 'CG',
				'name' => 'Congo'
			),
			array(
				'code' => 'CD',
				'name' => 'Congo, The Democratic Republic Of The'
			),
			array(
				'code' => 'CK',
				'name' => 'Cook Islands'
			),
			array(
				'code' => 'CR',
				'name' => 'Costa Rica'
			),
			array(
				'code' => 'CI',
				'name' => "Cote D'Ivoire"
			),
			array(
				'code' => 'HR',
				'name' => 'Croatia'
			),
			array(
				'code' => 'CU',
				'name' => 'Cuba'
			),
			array(
				'code' => 'CY',
				'name' => 'Cyprus'
			),
			array(
				'code' => 'CZ',
				'name' => 'Czech Republic'
			),
			array(
				'code' => 'DK',
				'name' => 'Denmark'
			),
			
			array(
				'code' => 'DJ',
				'name' => 'Djibouti'
			),
			array(
				'code' => 'DM',
				'name' => 'Dominica'
			),
			array(
				'code' => 'DO',
				'name' => 'Dominican Republic'
			),
			array(
				'code' => 'EC',
				'name' => 'Ecuador'
			),
			array(
				'code' => 'EG',
				'name' => 'Egypt'
			),
			array(
				'code' => 'SV',
				'name' => 'El Salvador'
			),
			array(
				'code' => 'GQ',
				'name' => 'Equatorial Guinea'
			),
			array(
				'code' => 'ER',
				'name' => 'Eritrea'
			),
			array(
				'code' => 'EE',
				'name' => 'Estonia'
			),
			array(
				'code' => 'ET',
				'name' => 'Ethiopia'
			),
			array(
				'code' => 'FK',
				'name' => 'Falkland Islands (Malvinas)'
			),
			array(
				'code' => 'FO',
				'name' => 'Faroe Islands'
			),
			array(
				'code' => 'FJ',
				'name' => 'Fiji'
			),
			array(
				'code' => 'FI',
				'name' => 'Finland'
			),
			array(
				'code' => 'FR',
				'name' => 'France'
			),
			array(
				'code' => 'GF',
				'name' => 'French Guiana'
			),
			array(
				'code' => 'PF',
				'name' => 'French Polynesia'
			),
			array(
				'code' => 'TF',
				'name' => 'French Southern Territories'
			),
			array(
				'code' => 'GA',
				'name' => 'Gabon'
			),
			array(
				'code' => 'GM',
				'name' => 'Gambia'
			),
			array(
				'code' => 'GE',
				'name' => 'Georgia'
			),
			array(
				'code' => 'DE',
				'name' => 'Germany'
			),
			array(
				'code' => 'GH',
				'name' => 'Ghana'
			),
			array(
				'code' => 'GI',
				'name' => 'Gibraltar'
			),
			array(
				'code' => 'GR',
				'name' => 'Greece'
			),
			array(
				'code' => 'GL',
				'name' => 'Greenland'
			),
			array(
				'code' => 'GD',
				'name' => 'Grenada'
			),
			array(
				'code' => 'GP',
				'name' => 'Guadeloupe'
			),
			array(
				'code' => 'GT',
				'name' => 'Guatemala',
				'state_label' => 'Department',
				'states' => array(
					"Alta Verapaz",
					"Baja Verapaz",
					"Chimaltenango",
					"Chiquimula",
					"El Progreso",
					"Escuintla",
					"Guatemala",
					"Huehuetenango",
					"Izabal",
					"Jalapa",
					"Jutiapa",
					"Peten",
					"Quetzaltenango",
					"Quiche",
					"Retalhuleu",
					"Sacatepequez",
					"San Marcos",
					"Santa Rosa",
					"Solola",
					"Suchitepequez",
					"Totonicapan",
					"Zacapa"
				)
			),
			array(
				'code' => 'GG',
				'name' => 'Guernsey'
			),
			array(
				'code' => 'GN',
				'name' => 'Guinea'
			),
			array(
				'code' => 'GW',
				'name' => 'Guinea Bissau'
			),
			array(
				'code' => 'GY',
				'name' => 'Guyana'
			),
			array(
				'code' => 'HT',
				'name' => 'Haiti'
			),
			array(
				'code' => 'HM',
				'name' => 'Heard Island And Mcdonald Islands'
			),
			array(
				'code' => 'VA',
				'name' => 'Holy See (Vatican City State)'
			),
			array(
				'code' => 'HN',
				'name' => 'Honduras'
			),
			array(
				'code' => 'HK',
				'name' => 'Hong Kong'
			),
			array(
				'code' => 'HU',
				'name' => 'Hungary'
			),
			array(
				'code' => 'IS',
				'name' => 'Iceland'
			),
			array(
				'code' => 'IN',
				'name' => 'India',
				'state_label' => 'State',
				'states' => array(
					'AN' => "Andaman and Nicobar",
					'AP' => "Andhra Pradesh",
					'AR' => "Arunachal Pradesh",
					'AS' => "Assam",
					'BR' => "Bihar",
					'CH' => "Chandigarh",
					'CT' => "Chattisgarh",
					'DN' => "Dadra and Nagar Haveli",
					'DD' => "Daman and Diu",
					'DL' => "Delhi",
					'GA' => "Goa",
					'GJ' => "Gujarat",
					'HR' => "Haryana",
					'HP' => "Himachal Pradesh",
					'JK' => "Jammu and Kashmir",
					'JH' => "Jharkhand",
					'KA' => "Karnataka",
					'KL' => "Kerala",
					'LD' => "Lakshadweep",
					'MP' => "Madhya Pradesh",
					'MH' => "Maharashtra",
					'MN' => "Manipur",
					'ML' => "Meghalaya",
					'MZ' => "Mizoram",
					'NL' => "Nagaland",
					'OR' => "Orissa",
					'PY' => "Puducherry",
					'PB' => "Punjab",
					'RJ' => "Rajasthan",
					'SK' => "Sikkim",
					'TN' => "Tamil Nadu",
					'TR' => "Tripura",
					'UP' => "Uttar Pradesh",
					'UT' => "Uttarakhand",
					'WB' => "West Bengal"
				)
			),
			array(
				'code' => 'ID',
				'name' => 'Indonesia'
			),
			array(
				'code' => 'IR',
				'name' => 'Iran, Islamic Republic Of'
			),
			array(
				'code' => 'IQ',
				'name' => 'Iraq'
			),
			array(
				'code' => 'IE',
				'name' => 'Ireland'
			),
			array(
				'code' => 'IM',
				'name' => 'Isle Of Man'
			),
			array(
				'code' => 'IL',
				'name' => 'Israel'
			),
			array(
				'code' => 'IT',
				'name' => 'Italy'
			),
			array(
				'code' => 'JM',
				'name' => 'Jamaica'
			),
			array(
				'code' => 'JP',
				'name' => 'Japan'
			),
			array(
				'code' => 'JE',
				'name' => 'Jersey'
			),
			array(
				'code' => 'JO',
				'name' => 'Jordan'
			),
			array(
				'code' => 'KZ',
				'name' => 'Kazakhstan'
			),
			array(
				'code' => 'KE',
				'name' => 'Kenya'
			),
			array(
				'code' => 'KI',
				'name' => 'Kiribati'
			),
			array(
				'code' => 'KP',
				'name' => "Korea, North"
			),
			array(
				'code' => 'KR',
				'name' => 'Korea, South'
			),
			array(
				'code' => 'KW',
				'name' => 'Kuwait'
			),
			array(
				'code' => 'KG',
				'name' => 'Kyrgyzstan'
			),
			array(
				'code' => 'LA',
				'name' => "Laos"
			),
			array(
				'code' => 'LV',
				'name' => 'Latvia'
			),
			array(
				'code' => 'LB',
				'name' => 'Lebanon'
			),
			array(
				'code' => 'LS',
				'name' => 'Lesotho'
			),
			array(
				'code' => 'LR',
				'name' => 'Liberia'
			),
			array(
				'code' => 'LY',
				'name' => 'Libya'
			),
			array(
				'code' => 'LI',
				'name' => 'Liechtenstein'
			),
			array(
				'code' => 'LT',
				'name' => 'Lithuania'
			),
			array(
				'code' => 'LU',
				'name' => 'Luxembourg'
			),
			array(
				'code' => 'MO',
				'name' => 'Macao'
			),
			array(
				'code' => 'MK',
				'name' => 'Macedonia'
			),
			array(
				'code' => 'MG',
				'name' => 'Madagascar'
			),
			array(
				'code' => 'MW',
				'name' => 'Malawi'
			),
			array(
				'code' => 'MY',
				'name' => 'Malaysia',
				'state_label' => 'State/Territory',
				'states' => array(
					'JHR' => "Johor",
					'KDH' => "Kedah",
					'KTN' => "Kelantan",
					'KUL' => "Kuala Lumpur",
					'LBN' => "Labuan",
					'MLK' => "Malacca",
					'NSN' => "Negeri Sembilan",
					'PHG' => "Pahang",
					'PRK' => "Perak",
					'PLS' => "Perlis",
					'PJY' => "Putrajaya",
					'SBH' => "Sabah",
					'SRW' => "Sarawak",
					'SGR' => "Selangor",
					'TRG' => "Terengganu"
				)
			),
			array(
				'code' => 'MV',
				'name' => 'Maldives'
			),
			array(
				'code' => 'ML',
				'name' => 'Mali'
			),
			array(
				'code' => 'MT',
				'name' => 'Malta'
			),
			array(
				'code' => 'MQ',
				'name' => 'Martinique'
			),
			array(
				'code' => 'MR',
				'name' => 'Mauritania'
			),
			array(
				'code' => 'MU',
				'name' => 'Mauritius'
			),
			array(
				'code' => 'YT',
				'name' => 'Mayotte'
			),
			array(
				'code' => 'MX',
				'name' => 'Mexico',
				'state_label' => 'State',
				'states' => array(
					'AG' => "Aguascalientes",
					'BJ' => "Baja California",
					'BS' => "Baja California Sur",
					'CI' => "Chihuahua",
					'CL' => "Colima",
					'CP' => "Campeche",
					'CU' => "Coahuila",
					'CH' => "Chiapas",
					'DF' => "Distrito Federal",
					'DG' => "Durango",
					'GR' => "Guerrero",
					'GJ' => "Guanajuato",
					'HG' => "Hidalgo",
					'JA' => "Jalisco",
					'MH' => "Michoacan",
					'MR' => "Morelos",
					'EM' => "Mexico",
					'NA' => "Nayarit",
					'NL' => "Nuevo Leon",
					'OA' => "Oaxaca",
					'PU' => "Puebla",
					'QR' => "Quintana Roo",
					'QA' => "Queretaro",
					'SI' => "Sinaloa",
					'SL' => "San Luis Potosi",
					'SO' => "Sonora",
					'TA' => "Tabasco",
					'TL' => "Tlaxcala",
					'TM' => "Tamaulipas",
					'VZ' => "Veracruz",
					'YC' => "Yucatan",
					'ZT' => "Zacatecas"
				)
			),
			array(
				'code' => 'MD',
				'name' => 'Moldova'
			),
			array(
				'code' => 'MC',
				'name' => 'Monaco'
			),
			array(
				'code' => 'MN',
				'name' => 'Mongolia'
			),
			array(
				'code' => 'ME',
				'name' => 'Montenegro'
			),
			array(
				'code' => 'MS',
				'name' => 'Montserrat'
			),
			array(
				'code' => 'MA',
				'name' => 'Morocco'
			),
			array(
				'code' => 'MZ',
				'name' => 'Mozambique'
			),
			array(
				'code' => 'MM',
				'name' => 'Myanmar'
			),
			array(
				'code' => 'NA',
				'name' => 'Namibia'
			),
			array(
				'code' => 'NR',
				'name' => 'Nauru'
			),
			array(
				'code' => 'NP',
				'name' => 'Nepal'
			),
			array(
				'code' => 'AN',
				'name' => 'Netherlands'
			),
			array(
				'code' => 'NL',
				'name' => 'Netherlands Antilles'
			),
			array(
				'code' => 'NC',
				'name' => 'New Caledonia'
			),
			array(
				'code' => 'NZ',
				'name' => 'New Zealand',
				'state_label' => 'Region',
				'states' => array(
					'AUK' => "Auckland",
					'BOP' => "Bay of Plenty",
					'CAN' => "Canterbury",
					'GIS' => "Gisborne",
					'HKB' => "Hawke's Bay",
					'MWT' => "Manawatu-Wanganui",
					'MBH' => "Marlborough",
					'NSN' => "Nelson",
					'NTL' => "Northland",
					'OTA' => "Otago",
					'STL' => "Southland",
					'TKI' => "Taranaki",
					'TAS' => "Tasman",
					'WKO' => "Waikato",
					'WGN' => "Wellington",
					'WTC' => "West Coast"
				)
			),
			array(
				'code' => 'NI',
				'name' => 'Nicaragua'
			),
			array(
				'code' => 'NE',
				'name' => 'Niger'
			),
			array(
				'code' => 'NG',
				'name' => 'Nigeria'
			),
			array(
				'code' => 'NU',
				'name' => 'Niue'
			),
			array(
				'code' => 'NF',
				'name' => 'Norfolk Island'
			),
			array(
				'code' => 'NO',
				'name' => 'Norway'
			),
			array(
				'code' => 'OM',
				'name' => 'Oman'
			),
			array(
				'code' => 'PK',
				'name' => 'Pakistan'
			),
			array(
				'code' => 'PS',
				'name' => 'Palestinian Territory, Occupied'
			),
			array(
				'code' => 'PA',
				'name' => 'Panama'
			),
			array(
				'code' => 'PG',
				'name' => 'Papua New Guinea'
			),
			array(
				'code' => 'PY',
				'name' => 'Paraguay'
			),
			array(
				'code' => 'PE',
				'name' => 'Peru'
			),
			array(
				'code' => 'PH',
				'name' => 'Philippines'
			),
			array(
				'code' => 'PN',
				'name' => 'Pitcairn'
			),
			array(
				'code' => 'PL',
				'name' => 'Poland'
			),
			array(
				'code' => 'PT',
				'name' => 'Portugal'
			),
			array(
				'code' => 'QA',
				'name' => 'Qatar'
			),
			array(
				'code' => 'RE',
				'name' => 'Reunion'
			),
			array(
				'code' => 'RO',
				'name' => 'Romania'
			),
			array(
				'code' => 'RU',
				'name' => 'Russia'
			),
			array(
				'code' => 'RW',
				'name' => 'Rwanda'
			),
			array(
				'code' => 'BL',
				'name' => 'Saint Barthelemy'
			),
			array(
				'code' => 'SH',
				'name' => 'Saint Helena'
			),
			array(
				'code' => 'KN',
				'name' => 'Saint Kitts And Nevis'
			),
			array(
				'code' => 'LC',
				'name' => 'Saint Lucia'
			),
			array(
				'code' => 'MF',
				'name' => 'Saint Martin'
			),
			array(
				'code' => 'PM',
				'name' => 'Saint Pierre And Miquelon'
			),
			array(
				'code' => 'VC',
				'name' => 'Saint Vincent'
			),
			array(
				'code' => 'WS',
				'name' => 'Samoa'
			),
			array(
				'code' => 'SM',
				'name' => 'San Marino'
			),
			array(
				'code' => 'ST',
				'name' => 'Sao Tome And Principe'
			),
			array(
				'code' => 'SA',
				'name' => 'Saudi Arabia'
			),
			array(
				'code' => 'SN',
				'name' => 'Senegal'
			),
			array(
				'code' => 'RS',
				'name' => 'Serbia'
			),
			array(
				'code' => 'SC',
				'name' => 'Seychelles'
			),
			array(
				'code' => 'SL',
				'name' => 'Sierra Leone'
			),
			array(
				'code' => 'SG',
				'name' => 'Singapore'
			),
			array(
				'code' => 'SK',
				'name' => 'Slovakia'
			),
			array(
				'code' => 'SI',
				'name' => 'Slovenia'
			),
			array(
				'code' => 'SB',
				'name' => 'Solomon Islands'
			),
			array(
				'code' => 'SO',
				'name' => 'Somalia'
			),
			array(
				'code' => 'ZA',
				'name' => 'South Africa'
			),
			array(
				'code' => 'GS',
				'name' => 'South Georgia And The South Sandwich Islands'
			),
			array(
				'code' => 'ES',
				'name' => 'Spain',
				'state_label' => 'Province',
				'states' => array(
					"A Coruna",
					"Alava",
					"Albacete",
					"Alicante",
					"Almeria",
					"Asturias",
					"Avila",
					"Badajoz",
					"Baleares",
					"Barcelona",
					"Burgos",
					"Caceres",
					"Cadiz",
					"Cantabria",
					"Castellon",
					"Ceuta",
					"Ciudad Real",
					"Cordoba",
					"Cuenca",
					"Girona",
					"Granada",
					"Guadalajara",
					"Guipuzcoa",
					"Huelva",
					"Huesca",
					"Jaen",
					"La Rioja",
					"Las Palmas",
					"Leon",
					"Lleida",
					"Lugo",
					"Madrid",
					"Malaga",
					"Melilla",
					"Murcia",
					"Navarra",
					"Ourense",
					"Palencia",
					"Pontevedra",
					"Salamanca",
					"Santa Cruz de Tenerife",
					"Segovia",
					"Sevilla",
					"Soria",
					"Tarragona",
					"Teruel",
					"Toledo",
					"Valencia",
					"Valladolid",
					"Vizcaya",
					"Zamora",
					"Zaragoza"
				)
			),
			array(
				'code' => 'LK',
				'name' => 'Sri Lanka'
			),
			array(
				'code' => 'SD',
				'name' => 'Sudan'
			),
			array(
				'code' => 'SR',
				'name' => 'Suriname'
			),
			array(
				'code' => 'SJ',
				'name' => 'Svalbard And Jan Mayen'
			),
			array(
				'code' => 'SZ',
				'name' => 'Swaziland'
			),
			array(
				'code' => 'SE',
				'name' => 'Sweden'
			),
			array(
				'code' => 'CH',
				'name' => 'Switzerland'
			),
			array(
				'code' => 'SY',
				'name' => 'Syria'
			),
			array(
				'code' => 'TW',
				'name' => 'Taiwan'
			),
			array(
				'code' => 'TJ',
				'name' => 'Tajikistan'
			),
			array(
				'code' => 'TZ',
				'name' => 'Tanzania'
			),
			array(
				'code' => 'TH',
				'name' => 'Thailand'
			),
			array(
				'code' => 'TL',
				'name' => 'Timor-Leste'
			),
			array(
				'code' => 'TG',
				'name' => 'Togo'
			),
			array(
				'code' => 'TK',
				'name' => 'Tokelau'
			),
			array(
				'code' => 'TO',
				'name' => 'Tonga'
			),
			array(
				'code' => 'TT',
				'name' => 'Trinidad and Tobago'
			),
			array(
				'code' => 'TN',
				'name' => 'Tunisia'
			),
			array(
				'code' => 'TR',
				'name' => 'Turkey'
			),
			array(
				'code' => 'TM',
				'name' => 'Turkmenistan'
			),
			array(
				'code' => 'TC',
				'name' => 'Turks And Caicos Islands'
			),
			array(
				'code' => 'TV',
				'name' => 'Tuvalu'
			),
			array(
				'code' => 'UG',
				'name' => 'Uganda'
			),
			array(
				'code' => 'UA',
				'name' => 'Ukraine'
			),
			array(
				'code' => 'AE',
				'name' => 'United Arab Emirates',
				'state_label' => 'Emirate',
				'states' => array(
					"Abu Dhabi",
					"Ajman",
					"Dubai",
					"Fujairah",
					"Ras al-Khaimah",
					"Sharjah",
					"Umm al-Quwain"
				)
			),
			array(
				'code' => 'UM',
				'name' => 'United States Minor Outlying Islands'
			),
			array(
				'code' => 'UY',
				'name' => 'Uruguay'
			),
			array(
				'code' => 'UZ',
				'name' => 'Uzbekistan'
			),
			array(
				'code' => 'VU',
				'name' => 'Vanuatu'
			),
			array(
				'code' => 'VE',
				'name' => 'Venezuela'
			),
			array(
				'code' => 'VN',
				'name' => 'Vietnam'
			),
			array(
				'code' => 'VG',
				'name' => 'Virgin Islands, British'
			),
			array(
				'code' => 'WF',
				'name' => 'Wallis And Futuna'
			),
			array(
				'code' => 'EH',
				'name' => 'Western Sahara'
			),
			array(
				'code' => 'YE',
				'name' => 'Yemen'
			),
			array(
				'code' => 'ZM',
				'name' => 'Zambia'
			),
			array(
				'code' => 'ZW',
				'name' => 'Zimbabwe'
			)
		);
	}
	
	// Select a country?
	if ($country)
	{
		foreach ($countries as $co)
		{
			if ($co['code'] == $country || $co['name'] == $country)
			{
				return $co;
			}
		}
	}
	
	return $countries;
}