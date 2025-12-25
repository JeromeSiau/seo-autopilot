import { Fragment, useState, useEffect } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { X, Activity, FileText } from 'lucide-react';
import { ActivityFeed } from './ActivityFeed';
import { AgentEvent, GeneratingArticle } from './types';
import clsx from 'clsx';

interface ActivityDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    events: AgentEvent[];
    activeAgents: Map<number, string[]>;
    generatingArticles: GeneratingArticle[];
}

export function ActivityDrawer({
    isOpen,
    onClose,
    events,
    activeAgents,
    generatingArticles,
}: ActivityDrawerProps) {
    const [selectedArticleId, setSelectedArticleId] = useState<number | null>(
        generatingArticles[0]?.id ?? null
    );

    // Update selected article when list changes
    useEffect(() => {
        if (selectedArticleId && !generatingArticles.find(a => a.id === selectedArticleId)) {
            if (generatingArticles.length > 0) {
                setSelectedArticleId(generatingArticles[0].id);
            } else {
                setSelectedArticleId(null);
            }
        } else if (!selectedArticleId && generatingArticles.length > 0) {
            setSelectedArticleId(generatingArticles[0].id);
        }
    }, [generatingArticles, selectedArticleId]);

    const filteredEvents = selectedArticleId
        ? events.filter(e => e.article_id === selectedArticleId)
        : events;

    const selectedArticle = generatingArticles.find(a => a.id === selectedArticleId);
    const totalActiveAgents = Array.from(activeAgents.values()).flat().length;

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
                    <div className="fixed inset-0 bg-surface-900/50 dark:bg-black/60 backdrop-blur-sm transition-opacity" />
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
                                    <div className="flex h-full flex-col bg-white dark:bg-surface-900 shadow-xl">
                                        {/* Header */}
                                        <div className="bg-surface-900 dark:bg-surface-800 px-4 py-5 sm:px-6">
                                            <div className="flex items-center justify-between">
                                                <Dialog.Title className="flex items-center gap-2 text-base font-semibold text-white">
                                                    <Activity size={20} className="text-primary-400" />
                                                    Activité des Agents
                                                    {totalActiveAgents > 0 && (
                                                        <span className="ml-2 px-2 py-0.5 text-xs bg-primary-500 rounded-full">
                                                            {totalActiveAgents} actif{totalActiveAgents > 1 ? 's' : ''}
                                                        </span>
                                                    )}
                                                </Dialog.Title>
                                                <button
                                                    type="button"
                                                    className="text-surface-400 hover:text-white transition-colors"
                                                    onClick={onClose}
                                                    aria-label="Fermer le panneau d'activité"
                                                >
                                                    <X size={24} />
                                                </button>
                                            </div>
                                        </div>

                                        {/* Article tabs (if multiple) */}
                                        {generatingArticles.length > 1 && (
                                            <div className="border-b border-surface-200 dark:border-surface-700 px-4 py-2 overflow-x-auto">
                                                <div className="flex gap-2">
                                                    {generatingArticles.map(article => {
                                                        const articleActiveAgents = activeAgents.get(article.id) || [];
                                                        const isSelected = article.id === selectedArticleId;

                                                        return (
                                                            <button
                                                                key={article.id}
                                                                onClick={() => setSelectedArticleId(article.id)}
                                                                className={clsx(
                                                                    'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
                                                                    isSelected
                                                                        ? 'bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-400'
                                                                        : 'text-surface-600 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800'
                                                                )}
                                                            >
                                                                <FileText size={14} />
                                                                <span className="max-w-[150px] truncate">{article.title}</span>
                                                                {articleActiveAgents.length > 0 && (
                                                                    <span className="w-2 h-2 bg-primary-500 rounded-full animate-pulse" />
                                                                )}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}

                                        {/* Selected article info */}
                                        {selectedArticle && (
                                            <div className="px-4 py-3 border-b border-surface-100 dark:border-surface-800 bg-surface-50 dark:bg-surface-800/50">
                                                <p className="text-sm font-medium text-surface-900 dark:text-white truncate">
                                                    {selectedArticle.title}
                                                </p>
                                                <p className="text-xs text-surface-500 dark:text-surface-400">
                                                    {selectedArticle.site_name}
                                                </p>
                                            </div>
                                        )}

                                        {/* Content */}
                                        <div className="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                                            {generatingArticles.length === 0 ? (
                                                <div className="text-center py-12">
                                                    <Activity size={48} className="mx-auto text-surface-300 dark:text-surface-600 mb-4" />
                                                    <p className="text-surface-500 dark:text-surface-400">
                                                        Aucun article en cours de génération
                                                    </p>
                                                </div>
                                            ) : (
                                                <ActivityFeed events={filteredEvents} groupByAgent={true} />
                                            )}
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
