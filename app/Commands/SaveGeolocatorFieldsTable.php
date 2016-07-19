<?php namespace App\Commands;

use App\GeolocatorField;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldBeQueued;

class SaveGeolocatorFieldsTable extends Command implements SelfHandling, ShouldBeQueued
{
    use InteractsWithQueue, SerializesModels;

    /**
     * Execute the command.
     */
    public function handle() {
        Log::info("Started backing up the Geolocator Fields table.");

        $table_path = $this->backup_filepath . "/geolocator_fields/";

        $row_id = DB::table('backup_partial_progress')->insertGetId(
            $this->makeBackupTableArray("geolocator_fields")
        );

        $this->backup_fs->makeDirectory($table_path);
        GeolocatorField::chunk(1000, function($geolocatorfields) use ($table_path, $row_id) {
            $count = 0;
            $all_geolocatorfields_data = new Collection();

            foreach ($geolocatorfields as $geolocatorfield) {
                $individual_geolocatorfield_data = new Collection();

                $individual_geolocatorfield_data->put("id", $geolocatorfield->id);
                $individual_geolocatorfield_data->put("rid", $geolocatorfield->rid);
                $individual_geolocatorfield_data->put("flid", $geolocatorfield->flid);
                $individual_geolocatorfield_data->put("locations",$geolocatorfield->locations);
                $individual_geolocatorfield_data->put("created_at", $geolocatorfield->created_at->toDateTimeString());
                $individual_geolocatorfield_data->put("updated_at", $geolocatorfield->updated_at->toDateTimeString());

                $all_geolocatorfields_data->push($individual_geolocatorfield_data);
                $count++;
            }

            DB::table("backup_partial_progress")->where("id", $row_id)->increment("progress", $count, ["updated_at" => Carbon::now()] );
            $increment = DB::table("backup_partial_progress")->where("id", $row_id)->pluck("progress");
            $this->backup_fs->put($table_path . $increment . ".json", json_encode($all_geolocatorfields_data));
        });
    }
}