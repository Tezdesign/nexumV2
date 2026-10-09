<?php

namespace App\Attribute;

/**
 * Put on a controller class or action: only logged in administrators may reach it.
 * Enforced by App\EventSubscriber\AccessGuardSubscriber (the app has no firewall access_control).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class RequireAdmin
{
}
