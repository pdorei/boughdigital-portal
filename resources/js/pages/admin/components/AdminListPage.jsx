import React from 'react';
import Card from '@/components/ui/Card';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingState from '@/components/ui/LoadingState';
import Pagination from '@/components/ui/Pagination';
import Table from '@/components/ui/Table';
import FilterBar from '@/components/forms/FilterBar';
import SearchInput from '@/components/forms/SearchInput';
import Select from '@/components/forms/Select';
import PageHeader from '@/components/layout/PageHeader';
import { usePaginatedList } from '@/hooks/usePaginatedList';
import { useWorkspace } from '@/hooks/useWorkspace';

export default function AdminListPage({
    title,
    description,
    icon,
    columns,
    fetcher,
    filters = [],
    searchPlaceholder = 'Search',
    emptyTitle,
    emptyCopy,
    rowKey = (row) => row.id,
}) {
    const { pageData } = useWorkspace();
    const list = usePaginatedList(fetcher, {
        initialFilters: Object.fromEntries([['search', ''], ...filters.map((filter) => [filter.name, ''])]),
        syncUrl: true,
    });

    return (
        <div className="page-stack">
            <PageHeader eyebrow="Super Admin" title={title} description={description} />
            <FilterBar>
                <SearchInput value={list.filters.search} onChange={(value) => list.setFilter('search', value)} placeholder={searchPlaceholder} />
                {filters.map((filter) => (
                    <Select
                        key={filter.name}
                        label={filter.label}
                        value={list.filters[filter.name]}
                        options={filter.options(pageData)}
                        onChange={(event) => list.setFilter(filter.name, event.target.value)}
                    />
                ))}
            </FilterBar>
            {list.error ? <ErrorState title={`Could not load ${title.toLowerCase()}`} copy={list.error} /> : null}
            {list.loading ? <LoadingState label={`Loading ${title.toLowerCase()}`} /> : (
                list.rows.length ? (
                    <Card>
                        <Table columns={columns} rows={list.rows} rowKey={rowKey} />
                        <Pagination meta={list.meta} onPageChange={list.setPage} />
                    </Card>
                ) : <EmptyState icon={icon} title={emptyTitle} copy={emptyCopy} />
            )}
        </div>
    );
}
