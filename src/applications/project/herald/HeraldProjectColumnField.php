<?php

class HeraldProjectColumnField extends ManiphestTaskHeraldField
{
    const FIELDCONST = 'projects.column';

    public function getHeraldFieldName()
    {
        return pht('Workboard card');
    }

    public function getHeraldFieldValue($object)
    {
        $object_phid = $object->getPHID();

        $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
            $object_phid,
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST
        );

        $engine = id(new PhabricatorBoardLayoutEngine())
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->setBoardPHIDs($project_phids)->setObjectPHIDs(array($object_phid))
            ->executeLayout();

        $result = array();
        foreach ($project_phids as $project_phid) {
            $columns = $engine->getObjectColumns($project_phid, $object_phid);
            $result = array_merge($result, array_keys($columns));
        }

        return reset($result);
    }

    public function getHeraldFieldConditions()
    {
        return array(
            HeraldAdapter::CONDITION_MOVED_TO,
        );
    }

    protected function getHeraldFieldStandardType()
    {
        return self::STANDARD_PHID;
    }

    protected function getDatasource()
    {
        return new PhabricatorProjectColumnDatasource();
    }

}