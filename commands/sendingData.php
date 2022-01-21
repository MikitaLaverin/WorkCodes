<?php

namespace App\Console\Commands;

use App\Kernel\Dvor24\Dashboard;
use App\Kernel\Dvor24\Facilities;
use App\Kernel\Dvor24\Users;
use Illuminate\Console\Command;

class sendingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendingData:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'потом описание на английском';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
	return 0;
    }
    public function statistickUser($userId)
    {
        $dashboard = new Dashboard();
        $object = new Facilities();
        $data = [];
        $data["userId"] = $userId;
        $data["sid"] = 'partner';
        $pageData['objects'] = $object->objects(["partnerId" => $userId, "count" => true]);
        $pageData['cameras'] = $dashboard->camOffline($data);
        $pageData['tickets'] = $dashboard->newTickets($data);
        $pageData['demo'] = $dashboard->getRemainingTime($userId);
        return $pageData;
    }
}
