import { Loader2, CheckCircle, Clock, AlertCircle } from 'lucide-react';

interface Props {
    status: 'pending' | 'running' | 'partial' | 'completed' | 'failed';
    pagesCount: number;
}

export function CrawlStatusIndicator({ status, pagesCount }: Props) {
    if (status === 'pending') return null;

    return (
        <div className="flex items-center gap-2 text-sm">
            {status === 'running' && (
                <>
                    <Loader2 className="h-4 w-4 animate-spin text-blue-500" />
                    <span className="text-muted-foreground">
                        Analyse en cours : {pagesCount} pages découvertes
                    </span>
                </>
            )}
            {status === 'completed' && (
                <>
                    <CheckCircle className="h-4 w-4 text-green-500" />
                    <span className="text-green-600">
                        {pagesCount} pages analysées
                    </span>
                </>
            )}
            {status === 'partial' && (
                <>
                    <Clock className="h-4 w-4 text-yellow-500" />
                    <span className="text-muted-foreground">
                        {pagesCount} pages (analyse approfondie en cours...)
                    </span>
                </>
            )}
            {status === 'failed' && (
                <>
                    <AlertCircle className="h-4 w-4 text-red-500" />
                    <span className="text-red-600">
                        Analyse partielle ({pagesCount} pages)
                    </span>
                </>
            )}
        </div>
    );
}
