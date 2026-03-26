<?php

namespace Database\Seeders;

use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Models\Canvas;
use App\Models\User;
use App\Models\Widget;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->withStreamerTeam()->create([
            'name' => 'MikeGamesEtc',
            'email' => 'mdcasso@gmail.com',
            'password' => Hash::make('Abc123!!!'),
        ]);

        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'created_by_user_id' => $user->id,
            'name' => 'My canvas',
        ]);

        $taskList = Widget::factory()->taskList()->create([
            'team_id' => $user->currentTeam->id,
            'created_by_user_id' => $user->id,
            'name' => 'Task List',
        ]);

        $canvas->widgetInstances()->create([
            'team_id' => $user->currentTeam->id,
            'widget_id' => $taskList->id,
            'source_kind' => WidgetSourceKind::Proprietary,
            'name' => null,
            'position_x' => 80,
            'position_y' => 80,
            'width' => 720,
            'height' => 560,
            'content_width' => 720,
            'content_height' => 560,
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
            'z_index' => 0,
            'is_visible' => true,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 720,
                    'frame_height' => 560,
                    'content_width' => 720,
                    'content_height' => 560,
                ],
            ],
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
            'preview_checked_at' => now(),
        ]);

        $pomodoro = Widget::factory()->pomodoro()->create([
            'team_id' => $user->currentTeam->id,
            'created_by_user_id' => $user->id,
            'name' => 'Pomodoro Timer',
        ]);

        $canvas->widgetInstances()->create([
            'team_id' => $user->currentTeam->id,
            'widget_id' => $pomodoro->id,
            'source_kind' => WidgetSourceKind::Proprietary,
            'name' => null,
            'position_x' => 960,
            'position_y' => 80,
            'width' => 520,
            'height' => 320,
            'content_width' => 520,
            'content_height' => 320,
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
            'z_index' => 1,
            'is_visible' => true,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 520,
                    'frame_height' => 320,
                    'content_width' => 520,
                    'content_height' => 320,
                ],
            ],
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
            'preview_checked_at' => now(),
        ]);

        $canvas->widgetInstances()->create([
            'team_id' => $user->currentTeam->id,
            'widget_id' => null,
            'source_kind' => WidgetSourceKind::RemoteUrl,
            'name' => 'SE Countdown',
            'embed_url' => 'https://widgets.streamelements.com/host/67c881040dc8c01c507bc287/151d82c8-eabf-4201-a08b-2c3a9c81ca8c/S5uGiyZFmn_eA-5R4Vnu2mcM1_erUdXf9mQMXiCo9qfwQ1fW',
            'position_x' => 160,
            'position_y' => 460,
            'width' => 760,
            'height' => 480,
            'content_width' => 760,
            'content_height' => 480,
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
            'z_index' => 2,
            'is_visible' => true,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 760,
                    'frame_height' => 480,
                    'content_width' => 760,
                    'content_height' => 480,
                ],
            ],
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
            'preview_checked_at' => now(),
        ]);
    }
}
