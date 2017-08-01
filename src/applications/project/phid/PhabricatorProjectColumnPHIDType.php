<?php

final class PhabricatorProjectColumnPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PCOL';

  public function getTypeName() {
    return pht('Project Column');
  }

  public function getTypeIcon() {
    return 'fa-columns bluegrey';
  }

  public function newObject() {
    return new PhabricatorProjectColumn();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorProjectColumnQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $column = $objects[$phid];

      $project = $column->getProject();

      $name = $project->getDisplayName().': '.$column->getDisplayName();

      $handle->setName($name);
      $handle->setURI('/project/board/'.$project->getID().'/');
      $handle->setTagColor($project->getColor());

      if ($column->isHidden()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

}
