<?php

namespace Sortable\Mapping;

use Doctrine\Common\EventManager;
use Gedmo\Sortable\SortableListener;

require_once __DIR__.'/MappingTestCase.php';

class AnnotationTest extends MappingTestCase
{
    /**
     * @test
     */
    public function shouldMapSortableEntity()
    {
        $evm = new EventManager();
        $evm->addEventSubscriber($sortable = new SortableListener());
        $em = $this->createEntityManager($evm);

        $meta = $em->getClassMetadata('Fixture\Sortable\Mapping');
        $exm = $sortable->getConfiguration($em, $meta->name);

        $this->assertMapping($exm);
    }
}
