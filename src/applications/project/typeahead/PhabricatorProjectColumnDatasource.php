<?php

class PhabricatorProjectColumnDatasource extends PhabricatorTypeaheadDatasource
{
    public function getBrowseTitle()
    {
        return pht('Browse Workboard Columns');
    }

    public function getPlaceholderText()
    {
        return pht('Type a workboard column name or project name...');
    }

    public function getDatasourceApplicationClass()
    {
        return 'PhabricatorProjectApplication';
    }

    public function loadResults()
    {
        $viewer = $this->getViewer();
        $is_browse = $this->getIsBrowse();

        $phid_type = new PhabricatorProjectColumnPHIDType();

        $query = id(new PhabricatorProjectColumnQuery())->withDatasourceQuery($this->getRawQuery());
        $columns = $this->executeQuery($query);

        $results = array();
        foreach ($columns as $column) {
            $project = $column->getProject();
            $project = id(new PhabricatorProjectQuery())
                ->setViewer($viewer)
                ->withPHIDs(array($project->getPHID()))
                ->needImages(true)
                ->executeOne();

            $name = array(
                $project->getDisplayName(),
                $column->getDisplayName(),
            );

            $display_name = implode(': ', $name);
            $name = implode(' ', $name);

            $closed = $column->isHidden() ? pht('Hidden') : null;

            $result = id(new PhabricatorTypeaheadResult())
                ->setName($name)
                ->setDisplayName($display_name)
                ->setDisplayType($column->getDisplayType())
                ->setImageURI($project->getProfileImageURI())
                ->setURI('/project/board/'.$project->getID().'/')
                ->setPHID($column->getPHID())
                ->setIcon($phid_type->getTypeIcon())
                ->setColor($this->getProjectColumnColor($column))
                ->setClosed($closed);

            if ($is_browse) {
                $result->addAttribute($phid_type->getTypeName());
            }

            $results[] = $result;
        }

        return $results;
    }

    private function getProjectColumnColor(PhabricatorProjectColumn $column)
    {
        $project = $column->getProject();

        if ($column->isHidden() || $project->isArchived()) {
            return PHUITagView::COLOR_DISABLED;
        }

        return $project->getColor();
    }
}