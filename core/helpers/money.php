<?php
/**
 * Format number as localized money string.
 *
 *		Usage example:
 *			{$price = 10}
 *			{$price|money} # $10.00
 *			{(-$price)|money:true} # ($10.00)
 *
 * @param amount Money value amount
 * @param format (Optional) Flag to display negative amount (default true)
 * @param negative (Optional) Flag to format amount with currency symbol and parantheses (default true)
 * @param locale (Optional) Locale flag related to 'setlocale' (default en_US.UTF-8)
*/
function money ($params, $format = true, $negative = true, $locale = null)
{
	$amount = is_array($params) ? $params['amount'] : $params;
	$negative = is_array($params) ? $params['negative'] ?: $negative : $negative;
	$format = is_array($params) ? $params['format'] ?: $format : $format;
	$locale = is_array($params) ? $params['locale'] ?: $locale : $locale;
	
	// Allow negative?
	$amount = ($negative || $amount > 0) ? $amount : 0;
	
	// Override default money locale?
	if ($locale)
	{
		// Character set optional (default UTF-8).
		$locale = strpos('.', $locale) === false ? $locale.".UTF-8" : $locale;
		
		// Save original.
		$orig_locale = setlocale(LC_ALL, 0);
		
		// Override.
		setlocale(LC_ALL, $locale);
	}
	
	// Use localeconv.
	$lc = localeconv();
	
	// Format with symbol?
	if ($format)
	{
		if ($amount < 0)
		{
			// Nevative value.
			$result = '('.$lc['currency_symbol'].number_format(
				abs($amount),
				$lc['frac_digits'],
				$lc['decimal_point'],
				$lc['thousands_sep']
			).')';
		}
		else
		{
			// Positive value.
			$result = $lc['currency_symbol'].number_format(
				$amount,
				$lc['frac_digits'],
				$lc['decimal_point'],
				$lc['thousands_sep']
			);
		}
	}
	else
	{
		// Number without currency symbol.
		$result = number_format(
			$amount,
			$lc['frac_digits'],
			$lc['decimal_point'],
			$lc['thousands_sep']
		);
	}
	
	// Reset locale?
	if ($orig_locale)
	{
		setlocale(LC_ALL, $orig_locale);
	}
	
	return $result;
}