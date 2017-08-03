<?php

/**
 * Class ManiphestTaskFastActionController
 */
class ManiphestTaskFastActionController extends PhabricatorController
{
    /**
     * @var string
     */
    private $verb;

    /**
     * @var int
     */
    private $taskId;

    /**
     * @param array $data
     */
    public function willProcessRequest(array $data)
    {
        $this->taskId = $data['id'];
        $this->verb = $data['verb'];
    }

    /**
     * Application router method.
     *
     * @return Aphront404Response
     */
    public function handleRequest()
    {
        $request = $this->getRequest();
        $viewer = $request->getUser();

        $task = id(new ManiphestTaskQuery())
            ->setViewer($viewer)
            ->withIds([$this->taskId])
            ->needSubscriberPHIDs(true)
            ->executeOne();

        if (!$task) {
            return new Aphront404Response();
        }

        if (!PhabricatorPolicyFilter::hasCapability($viewer, $task, PhabricatorPolicyCapability::CAN_EDIT)) {
            return new Aphront403Response();
        }

        switch ($this->verb) {
            case 'resolve':
                if ($task->isClosed()) {
                    return $this->redirectToTask($task);
                }

                $newTaskStatus = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
                break;

            case 'reopen':
                if (!$task->isClosed()) {
                    return $this->redirectToTask($task);
                }

                $newTaskStatus = ManiphestTaskStatus::STATUS_OPEN;
                break;

            default:
                return new Aphront404Response();
        }

        $xaction = id(new ManiphestTransaction())
            ->setTransactionType(ManiphestTaskStatusTransaction::TRANSACTIONTYPE)
            ->setNewValue($newTaskStatus);

        $editor = id(new ManiphestTransactionEditor())
            ->setActor($request->getUser())
            ->setContentSource(PhabricatorContentSource::newFromRequest($request))
            ->setContinueOnNoEffect(true)
            ->applyTransactions($task, [$xaction]);

        return $this->redirectToTask($task);
    }

    /**
     * @param ManiphestTask $task
     *
     * @return AphrontRedirectResponse
     */
    private function redirectToTask(ManiphestTask $task): AphrontRedirectResponse
    {
        return id(new AphrontRedirectResponse())->setURI('/'.$task->getMonogram());
    }
}