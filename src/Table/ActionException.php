<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use RuntimeException;

/**
 * Thrown by an action handler to refuse the action; the message is shown to the user.
 */
class ActionException extends RuntimeException
{
}
