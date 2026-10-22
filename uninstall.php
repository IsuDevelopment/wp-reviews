<?php
/**
 * Uninstall: drop tables and options.
 *
 * @package IsuDev\Reviews
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/isudev-reviews.php';

IsuDev\Reviews\Cron::unschedule();
IsuDev\Reviews\Schema::uninstall();
