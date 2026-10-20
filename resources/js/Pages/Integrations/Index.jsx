import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

export default function Index({ linkedIntegrations }) {
    const [activeFiles, setActiveFiles] = useState([]);
    const [selectedAccount, setSelectedAccount] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    const availableProviders = [
        { id: 'google', name: 'Google Drive', description: 'Stream files and access cloud storage folders.' },
        { id: 'dropbox', name: 'Dropbox Cloud', description: 'Sync system backup folders and assets.' },
    ];

    // Async AJAX call to fetch live files from port 8080 without full page reloads
    const loadAccountFiles = async (integrationId, accountEmail) => {
        setIsLoading(true);
        setSelectedAccount(accountEmail);
        setActiveFiles([]);

        try {
            const response = await axios.get(route('integrations.files', { integration_id: integrationId }));
            setActiveFiles(response.data);
        } catch (error) {
            console.error("Error fetching integration files:", error);
            alert("Failed to read external platform file structure.");
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Integrations Dashboard</h2>}
        >
            <Head title="Integrations" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">

                    {/* Catalog Grid Matrix */}
                    <div className="grid md:grid-cols-2 gap-6">
                        {availableProviders.map((provider) => {
                            const connections = linkedIntegrations[provider.id] || [];

                            return (
                                <div key={provider.id} className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 flex flex-col justify-between border border-gray-100 dark:border-gray-700">
                                    <div>
                                        <div className="flex items-center justify-between mb-2">
                                            <h3 className="text-lg font-bold text-gray-900 dark:text-gray-100">{provider.name}</h3>
                                            <a
                                                href={route('auth.redirect', { provider: provider.id })}
                                                className="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-md hover:bg-indigo-500 font-semibold transition"
                                            >
                                                + Link Account
                                            </a>
                                        </div>
                                        <p className="text-sm text-gray-500 mb-4">{provider.description}</p>
                                    </div>

                                    <div className="space-y-2 mt-4">
                                        {connections.length === 0 ? (
                                            <p className="text-xs text-gray-400 italic py-2">No connections linked yet.</p>
                                        ) : (
                                            connections.map((conn) => (
                                                <div
                                                    key={conn.id}
                                                    className="flex justify-between items-center bg-gray-50 dark:bg-gray-900/50 p-3 rounded-lg border dark:border-gray-700"
                                                >
                                                    <div
                                                        key={conn.id}
                                                        onClick={() => loadAccountFiles(conn.id, conn.account_email)}
                                                        className="flex justify-between items-center bg-gray-50 dark:bg-gray-900/50 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 cursor-pointer transition"
                                                    >
                                                        <div className="overflow-hidden">
                                                            <p className="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">{conn.account_name || 'Active Workspace'}</p>
                                                            <p className="text-[11px] text-gray-500 truncate">{conn.account_email}</p>
                                                        </div>
                                                    </div>
                                                    <Link
                                                        href={route('integrations.sync', { integration_id: conn.id })}
                                                        method="post"
                                                        as="button"
                                                        className="text-[10px] bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-bold px-2 py-1 rounded border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-900/60 transition"
                                                    >
                                                        Sync Files
                                                    </Link>
                                                    <Link
                                                        href={route('integrations.destroy', { integration_id: conn.id })}
                                                        method="delete"
                                                        as="button"
                                                        className="ml-4 text-[10px] text-red-600 hover:text-red-500 font-bold px-2 py-1 rounded border border-red-200 dark:border-red-900/50 bg-white dark:bg-gray-800 transition"
                                                    >
                                                        Disconnect
                                                    </Link>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Async File Explorer Section */}
                    {selectedAccount && (
                        <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-100 dark:border-gray-700">
                            <div className="flex justify-between items-center mb-4 border-b pb-4 dark:border-gray-700">
                                <h3 className="text-lg font-bold text-gray-900 dark:text-gray-100">
                                    File Navigator: <span className="text-indigo-600 dark:text-indigo-400 font-normal">{selectedAccount}</span>
                                </h3>
                                <button
                                    onClick={() => setSelectedAccount(null)}
                                    className="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                >
                                    Close Explorer
                                </button>
                            </div>

                            {isLoading ? (
                                <div className="text-center py-12 text-sm text-gray-500">
                                    Connecting to secure cloud server and parsing indices...
                                </div>
                            ) : activeFiles.length === 0 ? (
                                <div className="text-center py-12 text-sm text-gray-400 italic">
                                    No items or documents found inside this cloud workspace directory root.
                                </div>
                            ) : (
                                <ul className="divide-y divide-gray-100 dark:divide-gray-700 border dark:border-gray-700 rounded-lg overflow-hidden">
                                    {activeFiles.map((file) => (
                                        <li key={file.id} className="p-3 hover:bg-gray-50 dark:hover:bg-gray-900/40 flex items-center space-x-3 text-sm text-gray-800 dark:text-gray-200">
                                            <svg className="h-5 w-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            <span className="truncate">{file.name}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
