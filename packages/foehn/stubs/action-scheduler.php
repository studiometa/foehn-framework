<?php

/**
 * Action Scheduler function stubs for static analysis.
 *
 * These functions are provided by woocommerce/action-scheduler,
 * which is a suggested (not required) dependency.
 *
 * @see https://actionscheduler.org/api/
 */

/**
 * Schedule an action to run once at a specific time.
 *
 * @param int $timestamp Unix timestamp for when to run the action
 * @param string $hook Action hook name
 * @param array<mixed> $args Arguments to pass to the action
 * @param string $group Action group
 * @param bool $unique Whether the action should be unique
 * @return int Action ID
 */
function as_schedule_single_action(
    int $timestamp,
    string $hook,
    array $args = [],
    string $group = '',
    bool $unique = false,
): int {}

/**
 * Schedule a recurring action.
 *
 * @param int $timestamp Unix timestamp for first run
 * @param int $interval_in_seconds Interval between runs in seconds
 * @param string $hook Action hook name
 * @param array<mixed> $args Arguments to pass to the action
 * @param string $group Action group
 * @param bool $unique Whether the action should be unique
 * @return int Action ID
 */
function as_schedule_recurring_action(
    int $timestamp,
    int $interval_in_seconds,
    string $hook,
    array $args = [],
    string $group = '',
    bool $unique = false,
): int {}

/**
 * Check if there is a scheduled action matching the given criteria.
 *
 * @param string $hook Action hook name
 * @param array<mixed>|null $args Arguments to match
 * @param string $group Action group
 * @return bool Whether a matching action exists
 */
function as_has_scheduled_action(string $hook, ?array $args = null, string $group = ''): bool {}

/**
 * Unschedule all actions matching the given criteria.
 *
 * @param string $hook Action hook name
 * @param array<mixed>|null $args Arguments to match
 * @param string $group Action group
 */
function as_unschedule_all_actions(string $hook, ?array $args = null, string $group = ''): void {}
