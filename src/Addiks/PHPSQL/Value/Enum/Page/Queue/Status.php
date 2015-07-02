<?php
/**
 * Copyright (C) 2013  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 * @package Addiks
 */

namespace Addiks\PHPSQL\Value\Enum\Page\Queue;

use Addiks\PHPSQL\Value\Enum;

class Status extends Enum
{
    
    /**
     * Queue-Job with this status has are currently in the process of being written to the queue.
     * A job is not guaranteed to be executed as long as it is not accepted (fully written to the queue).
     * When a job cannot be written completely to the queue, it will be cancelled by the next process,
     * in that case, it gets the new status "CANCELLED".
     * @var int
     */
    const QUEUEING    = 0x01;
    
    /**
     * This status indicates that the job was successfully and completely written to the queue.
     * It will stay in this status while it is waiting to be executed.
     * The job wil be executed as soon as all related locks get released.
     *
     * When the process responsible for this job dies, the process of the next job is
     * responsible for recognising that and cancelling it/perform a rollback.
     * (Every process waiting watches the process he is waiting for.)
     * @var int
     */
    const WAITING     = 0x02;
    
    /**
     * Running jobs are currently written from the queue to the database.
     * They lock and unlock the tables.
     *
     * When the process responsible for this job dies, the process of the next job is
     * responsible for recognising that and cancelling it/perform a rollback.
     * (Every process waiting watches the process he is waiting for.)
     * @var int
     */
    const RUNNING     = 0x03;
    
    /**
     * Finished jobs have been completely written to the database.
     * All locks are released.
     *
     * When all jobs in a queue are finished or cancelled, the queue gets deleted.
     * That happens when a new queue gets started because the old gets too big.
     * @var int
     */
    const FINISHED    = 0x04;
    
    /**
     * A job that was cancelled has either raised an error, died or performed a rollback.
     * It does not get written to the database.
     * @var int
     */
    const CANCELLED   = 0x05;
}
