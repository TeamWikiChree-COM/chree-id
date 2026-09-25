import { router } from '@inertiajs/react';
import ActionRow from '../ActionRow';
import OutlinedList from '../OutlinedList';

/** DashboardController が渡すプラグインの入口 */
export interface AvailablePlugin {
    href: string;
    label: string;
    description: string | null;
}

interface PluginListProps {
    plugins: AvailablePlugin[];
}

/**
 * 連携しているサービスで使えるプラグインの一覧。
 */
export default function PluginList({ plugins }: PluginListProps) {
    return (
        <OutlinedList>
            {plugins.map((plugin) => (
                <ActionRow
                    key={plugin.href}
                    label={plugin.label}
                    description={plugin.description ?? undefined}
                    onClick={() => router.get(plugin.href)}
                />
            ))}
        </OutlinedList>
    );
}
