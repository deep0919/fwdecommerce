<?php
/**
 * REST helper: delete.
 *
 *		Usage example:
 *			{get $result from "/products/slug" [is_active => true]}
 *			{$result = get("/products/slug", [is_active => true])}
 *			{$result = "/products/slug"|get:[is_active => true]}
 */
function get ($resource, $data = null)
{
	return Controller::__rest('GET', $resource, $data);
}