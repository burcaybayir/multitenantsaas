<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/*
 * Feature tests hit real MySQL. DatabaseTruncation rather than
 * RefreshDatabase: provisioning runs CREATE DATABASE / CREATE USER, which
 * MySQL auto-commits, so wrapping a test in a transaction can't work.
 */
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class, InteractsWithTenants::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
