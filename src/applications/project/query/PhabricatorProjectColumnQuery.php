<?php

final class PhabricatorProjectColumnQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $projectPHIDs;
  private $proxyPHIDs;
  private $statuses;
  private $datasourceQuery;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProjectPHIDs(array $project_phids) {
    $this->projectPHIDs = $project_phids;
    return $this;
  }

  public function withProxyPHIDs(array $proxy_phids) {
    $this->proxyPHIDs = $proxy_phids;
    return $this;
  }

  public function withStatuses(array $status) {
    $this->statuses = $status;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorProjectColumn();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $page) {
    $projects = array();

    $project_phids = array_filter(mpull($page, 'getProjectPHID'));
    if ($project_phids) {
      $projects = id(new PhabricatorProjectQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($project_phids)
        ->execute();
      $projects = mpull($projects, null, 'getPHID');
    }

    foreach ($page as $key => $column) {
      $phid = $column->getProjectPHID();
      $project = idx($projects, $phid);
      if (!$project) {
        $this->didRejectResult($page[$key]);
        unset($page[$key]);
        continue;
      }
      $column->attachProject($project);
    }

    $proxy_phids = array_filter(mpull($page, 'getProjectPHID'));

    return $page;
  }

  protected function didFilterPage(array $page) {
    $proxy_phids = array();
    foreach ($page as $column) {
      $proxy_phid = $column->getProxyPHID();
      if ($proxy_phid !== null) {
        $proxy_phids[$proxy_phid] = $proxy_phid;
      }
    }

    if ($proxy_phids) {
      $proxies = id(new PhabricatorObjectQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($proxy_phids)
        ->execute();
      $proxies = mpull($proxies, null, 'getPHID');
    } else {
      $proxies = array();
    }

    foreach ($page as $key => $column) {
      $proxy_phid = $column->getProxyPHID();

      if ($proxy_phid !== null) {
        $proxy = idx($proxies, $proxy_phid);

        // Only attach valid proxies, so we don't end up getting surprsied if
        // an install somehow gets junk into their database.
        if (!($proxy instanceof PhabricatorColumnProxyInterface)) {
          $proxy = null;
        }

        if (!$proxy) {
          $this->didRejectResult($column);
          unset($page[$key]);
          continue;
        }
      } else {
        $proxy = null;
      }

      $column->attachProxy($proxy);
    }

    return $page;
  }

  protected function getPrimaryTableAlias() {
    return 'col';
  }

  protected function buildSelectClauseParts(AphrontDatabaseConnection $conn) {
    $parts = parent::buildSelectClauseParts($conn);

    $parts[] = 'col.*';

    if ($this->shouldJoinProjectTable()) {
      $parts[] = 'proj.name projectName';
    }

    return $parts;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinProjectTable()) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T proj ON col.projectPHID = proj.phid',
        id(new PhabricatorProject())->getTableName()
      );
    }

    return $joins;
  }

  private function shouldJoinProjectTable() {
    return (strlen($this->datasourceQuery) ? true : false);
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'col.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'col.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->projectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'col.projectPHID IN (%Ls)',
        $this->projectPHIDs);
    }

    if ($this->proxyPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'col.proxyPHID IN (%Ls)',
        $this->proxyPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'col.status IN (%Ld)',
        $this->statuses);
    }

    if (strlen($this->datasourceQuery)) {
      $where[] = qsprintf(
        $conn,
        'LOWER(col.name) LIKE LOWER(%>) OR proj.name LIKE %>',
        $this->datasourceQuery,
        $this->datasourceQuery
      );
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

}
