import Chart from 'chart.js/auto';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { bacaDataGrafik, gambarGrafik, konfirmasi, pantauGrafik, pasangPeringatanSwal } from './grafik';

// Expose globally so Livewire @script blocks can use them.
window.Chart = Chart;
window.Swal = Swal;
window.Grafik = { bacaDataGrafik, gambarGrafik, konfirmasi, pantauGrafik, pasangPeringatanSwal };
