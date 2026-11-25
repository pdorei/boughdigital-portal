import React, { useState } from 'react';
import Tabs from '@/components/ui/Tabs';
import ArticleManager from '@/pages/clients/components/content/articles/ArticleManager';
import CalendarManager from '@/pages/clients/components/content/calendar/CalendarManager';
import PillarManager from '@/pages/clients/components/content/pillars/PillarManager';
import { tabFromUrl, writeTabToUrl } from '@/utils/tabs';

const SECTIONS = ['Calendar', 'Pillars', 'Articles'];

/**
 * Editorial planning and the article library. Separate from Documents, which
 * holds files and reference material.
 */
export default function ContentSection({ client, base, writers, canCreateArticle }) {
    const [section, setSection] = useState(() => tabFromUrl('section', SECTIONS, 'Calendar'));

    function change(next) {
        setSection(next);
        writeTabToUrl('section', next);
    }

    return (
        <div className="form-stack">
            <Tabs tabs={SECTIONS} activeTab={section} onChange={change} label="Content sections" />
            {section === 'Calendar' ? <CalendarManager client={client} base={base} writers={writers} canCreateArticle={canCreateArticle} /> : null}
            {section === 'Pillars' ? <PillarManager client={client} /> : null}
            {section === 'Articles' ? <ArticleManager client={client} base={base} writers={writers} /> : null}
        </div>
    );
}
