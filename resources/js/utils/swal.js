import Swal from 'sweetalert2';

export const useSwal = () => {
    const isDark = document.documentElement.classList.contains('dark');
    
    return Swal.mixin({
        customClass: {
            popup: isDark ? 'bg-gray-900 border border-gray-800 text-white rounded-2xl shadow-2xl' : 'bg-white border border-gray-200 text-gray-900 rounded-2xl shadow-2xl',
            title: 'text-xl font-cinzel font-black',
            htmlContainer: 'text-sm text-gray-600 dark:text-gray-400',
            confirmButton: 'px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-sm font-black uppercase tracking-widest border border-purple-500/30 transition-all shadow-lg shadow-purple-500/20 mx-2',
            cancelButton: 'px-5 py-2.5 rounded-xl bg-gray-200 hover:bg-gray-300 text-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-white text-sm font-black uppercase tracking-widest transition-all mx-2',
        },
        buttonsStyling: false,
        background: isDark ? '#111827' : '#ffffff', // Taildwind gray-900 / white
        color: isDark ? '#ffffff' : '#111827',
    });
};

export const confirmAction = async (title, text = '', confirmText = 'Confirmar', cancelText = 'Cancelar') => {
    const swal = useSwal();
    const result = await swal.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true
    });
    return result.isConfirmed;
};

export const showAlert = (title, text = '', icon = 'info') => {
    const swal = useSwal();
    return swal.fire({
        title,
        text,
        icon,
        confirmButtonText: 'Aceptar'
    });
};

export const showToast = (title, icon = 'success') => {
    const isDark = document.documentElement.classList.contains('dark');
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: isDark ? '#111827' : '#ffffff',
        color: isDark ? '#ffffff' : '#111827',
        customClass: {
            popup: isDark ? 'border border-gray-800 shadow-xl' : 'border border-gray-200 shadow-xl',
        }
    });

    Toast.fire({
        icon,
        title
    });
};