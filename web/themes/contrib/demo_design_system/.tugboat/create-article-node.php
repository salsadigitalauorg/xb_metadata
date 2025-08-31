<?php

declare(strict_types=1);

// We assume the "Standard" profile is installed at this point, along with the
// Experience Builder modules.

use Drupal\node\Entity\Node;

$node = Node::create([
  'type' => 'article',
  'title' => 'Test article node for XB+SDDS (go to /xb/node/1 to test)',
]);
$node->save();
