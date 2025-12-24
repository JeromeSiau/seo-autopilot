import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { X, Activity } from 'lucide-react';
import { ActivityFeed } from './ActivityFeed';

interface AgentEvent {
    id: number;
    agent_type: string;
    event_type: string;
    message: string;
    reasoning: string | null;
    progress_current: number | null;
    progress_total: number | null;
    progress_percent: number | null;
    created_at: string;
}

interface ActivityDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    events: AgentEvent[];
    activeAgents: string[];
    articleTitle?: string;
}

export function ActivityDrawer({
    isOpen,
    onClose,
    events,
    activeAgents,
    articleTitle
}: ActivityDrawerProps) {
    return (
        <Transition.Root show={isOpen} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                <Transition.Child
                    as={Fragment}
                    enter="ease-in-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in-out duration-300"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                </Transition.Child>

                <div className="fixed inset-0 overflow-hidden">
                    <div className="absolute inset-0 overflow-hidden">
                        <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                            <Transition.Child
                                as={Fragment}
                                enter="transform transition ease-in-out duration-300"
                                enterFrom="translate-x-full"
                                enterTo="translate-x-0"
                                leave="transform transition ease-in-out duration-300"
                                leaveFrom="translate-x-0"
                                leaveTo="translate-x-full"
                            >
                                <Dialog.Panel className="pointer-events-auto w-screen max-w-md">
                                    <div className="flex h-full flex-col overflow-y-scroll bg-white shadow-xl">
                                        {/* Header */}
                                        <div className="bg-gray-900 px-4 py-6 sm:px-6">
                                            <div className="flex items-center justify-between">
                                                <Dialog.Title className="flex items-center gap-2 text-base font-semibold text-white">
                                                    <Activity size={20} />
                                                    Activité des Agents
                                                    {activeAgents.length > 0 && (
                                                        <span className="ml-2 px-2 py-0.5 text-xs bg-blue-500 rounded-full">
                                                            {activeAgents.length} actif{activeAgents.length > 1 ? 's' : ''}
                                                        </span>
                                                    )}
                                                </Dialog.Title>
                                                <button
                                                    type="button"
                                                    className="text-gray-400 hover:text-white"
                                                    onClick={onClose}
                                                >
                                                    <X size={24} />
                                                </button>
                                            </div>
                                            {articleTitle && (
                                                <p className="mt-1 text-sm text-gray-400">
                                                    {articleTitle}
                                                </p>
                                            )}
                                        </div>

                                        {/* Content */}
                                        <div className="flex-1 px-4 py-4 sm:px-6">
                                            <ActivityFeed events={events} groupByAgent={true} />
                                        </div>
                                    </div>
                                </Dialog.Panel>
                            </Transition.Child>
                        </div>
                    </div>
                </div>
            </Dialog>
        </Transition.Root>
    );
}
