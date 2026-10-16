import Chart from 'chart.js/auto';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

// Expose globally so Livewire @script blocks can use them.
window.Chart = Chart;
window.Swal = Swal;
