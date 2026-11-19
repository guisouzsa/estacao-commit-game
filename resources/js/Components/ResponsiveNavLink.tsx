import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-[#b4da33] bg-white/10 text-[#b4da33] focus:border-[#b4da33]'
                    : 'border-transparent text-white/70 hover:border-[#f481c6] hover:bg-white/5 hover:text-white focus:border-[#f481c6] focus:bg-white/5 focus:text-white'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
