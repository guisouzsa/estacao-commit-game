<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE TABLE jobs (id bigserial PRIMARY KEY, queue varchar(255) NOT NULL, payload text NOT NULL, attempts smallint NOT NULL, reserved_at integer NULL, available_at integer NOT NULL, created_at integer NOT NULL)');
        DB::statement('CREATE INDEX jobs_queue_index ON jobs (queue)');

        DB::statement('CREATE TABLE job_batches (id varchar(255) PRIMARY KEY, name varchar(255) NOT NULL, total_jobs integer NOT NULL, pending_jobs integer NOT NULL, failed_jobs integer NOT NULL, failed_job_ids text NOT NULL, options text NULL, cancelled_at integer NULL, created_at integer NOT NULL, finished_at integer NULL)');
        DB::statement('CREATE TABLE failed_jobs (id bigserial PRIMARY KEY, uuid varchar(255) NOT NULL UNIQUE, connection text NOT NULL, queue text NOT NULL, payload text NOT NULL, exception text NOT NULL, failed_at timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
