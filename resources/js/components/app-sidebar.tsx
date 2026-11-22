import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    FolderGit2,
    ReceiptText,
    Scissors,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { agenda } from '@/routes';
import { index as estornos } from '@/routes/estornos';
import { index as profissionais } from '@/routes/profissionais';
import { index as servicos } from '@/routes/servicos';
import type { NavItem } from '@/types';

type Pendencias = { estornos: number } | null;

const footerNavItems: NavItem[] = [
    {
        title: 'Código no GitHub',
        href: 'https://github.com/rafaelamiranda/agendamentos-laravel',
        icon: FolderGit2,
    },
];

export function AppSidebar() {
    const { pendencias } = usePage<{ pendencias: Pendencias }>().props;

    const mainNavItems: NavItem[] = [
        { title: 'Agenda', href: agenda(), icon: CalendarDays },
        { title: 'Profissionais', href: profissionais(), icon: Users },
        { title: 'Serviços', href: servicos(), icon: Scissors },
        {
            title: 'Estornos',
            href: estornos(),
            icon: ReceiptText,
            badge: pendencias?.estornos,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={agenda()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
